<?php

namespace App\Services;

use App\Exceptions\ActionFailException;
use App\Repositories\ToolRepository;
use App\Models\Setting;
use Carbon\Carbon;
use App\Jobs\SendCheckinMailTool;
use App\Jobs\SendCheckoutMailTool;
use App\Jobs\SendConfirmMailTool;
use App\Repositories\UserRepository;
use Illuminate\Http\Response;

class ToolService
{
    private $toolRepository;
    private $userRepository;
    public function __construct(
        ToolRepository $toolRepository,
        UserRepository $userRepository
    ) {
        $this->toolRepository = $toolRepository;
        $this->userRepository = $userRepository;
    }

    public function index($request)
    {
        return $this->toolRepository->index($request);
    }

    public function getTotalDetail($request)
    {
        return $this->toolRepository->getTotalDetail($request);
    }

    public function getToolAssignList($request)
    {
        return $this->toolRepository->getToolAssignList($request);
    }

    public function store($request)
    {
        return $this->toolRepository->store($request);
    }

    public function update($request, $id)
    {
        $tool = $this->toolRepository->getToolById($id);
        $origin_assigned_status = $tool->assigned_status;
        $request_assigned_status = $request['assigned_status'] ?? null;
        $request_reason = $request['reason'] ?? "";
        $update_type = null;
        $user = null;
        if ($tool->assigned_to) {
            $user = $this->userRepository->getUserById($tool->assigned_to);
        }

        if (
            !$user
            || !$request['assigned_status']
            || $origin_assigned_status === $request_assigned_status
        ) {
            return $this->toolRepository->update($request, $id, config('enum.update_type.DEFAULT'));
        }

        if ($request_assigned_status == config('enum.assigned_status.ACCEPT')) {
            if ($tool->withdraw_from) {
                $is_confirm = 'đã xác nhận thu hồi';
                $subject = 'Mail xác nhận thu hồi tool';
                $update_type = config('enum.update_type.ACCEPT_CHECKIN');
                $this->sendMailConfirm($user, $tool->name, $is_confirm, $subject);
            } else {
                $is_confirm = 'đã xác nhận cấp phát';
                $subject = 'Mail xác nhận cấp phát tool';
                $update_type = config('enum.update_type.ACCEPT_CHECKOUT');
                $this->sendMailConfirm($user, $tool->name, $is_confirm, $subject);
            }
        } elseif ($request_assigned_status == config('enum.assigned_status.REJECT')) {
            if ($tool->withdraw_from) {
                $is_confirm = 'đã từ chối thu hồi';
                $subject = 'Mail từ chối thu hồi tool';
                $reason = 'Lý do: ' . $request_reason;
                $update_type = config('enum.update_type.REJECT_CHECKIN');
                $this->sendMailConfirm($user, $tool->name, $is_confirm, $subject, $reason);
            } else {
                $is_confirm = 'đã từ chối nhận';
                $subject = 'Mail từ chối nhận tool';
                $reason = 'Lý do: ' . $request_reason;
                $update_type = config('enum.update_type.REJECT_CHECKOUT');
                $this->sendMailConfirm($user, $tool, $is_confirm, $subject, $reason);
            }
        }

        return $this->toolRepository->update($request, $id, $update_type);
    }

    public function delete($id)
    {
        return $this->toolRepository->delete($id);
    }

    public function checkout($request, $tool_id)
    {
        $tool = $this->toolRepository->getToolOrFailById($tool_id);
        if (!$tool->availableForCheckout()) {
            throw new ActionFailException(
                'error',
                null,
                trans('admin/tools/message.checkout.not_available'),
                Response::HTTP_BAD_REQUEST
            );
        }
        $target = $this->userRepository->getUserById($request['assigned_to']);
        $note = request('note', null);
        $checkout_date = $request['checkout_at'];
        $tool->status_id = config('enum.status_id.ASSIGN');
        if (!$tool->checkOut($target, $checkout_date, $tool->name, config('enum.assigned_status.WAITINGCHECKOUT'), $note)) {

            throw new ActionFailException(
                'error',
                null,
                $tool->getErrors(),
                Response::HTTP_BAD_REQUEST
            );
        }
        $this->sendMailCheckout($target, $tool->name);
        return ['tool' => e($tool->name)];
    }

    public function checkin($request, $tool_id)
    {
        $tool = $this->toolRepository->getToolOrFailById($tool_id);
        if (is_null($target = $tool->assigned_to)) {
            throw new ActionFailException(
                'error',
                ['name' => e($tool->name)],
                trans('admin/tools/message.checkin.already_checked_in'),
                Response::HTTP_BAD_REQUEST
            );
        }
        if (!$tool->availableForCheckin()) {
            throw new ActionFailException(
                'error',
                ['tool' => e($tool->name)],
                trans('admin/tools/message.checkin.not_available'),
                Response::HTTP_BAD_REQUEST
            );
        }

        $checkin_date = $request['checkin_at'] ?? "";
        $note = $request['notes'] ?? "";
        $target = $this->userRepository->getUserById($tool->assigned_to);

        if (!$tool->checkIn($target, $checkin_date, $tool->name, config('enum.assigned_status.WAITINGCHECKIN'), $note)) {
            throw new ActionFailException(
                'error',
                null,
                $tool->getErrors(),
                Response::HTTP_BAD_REQUEST
            );
        }
        $this->sendMailCheckin($tool->assignedTo, $tool);
        return ['tool' => e($tool->name)];
    }

    public function getToolById($id)
    {
        return $this->toolRepository->getToolById($id);
    }

    private function sendMailCheckout($assigned_user, $tool_name)
    {
        $user_email = $assigned_user->email;
        $user_name = $assigned_user->first_name . ' ' . $assigned_user->last_name;
        $current_time = Carbon::now();
        $data = [
            'user_name' => $user_name,
            'tool_name' => $tool_name,
            'count' => 1,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendCheckoutMailTool::dispatch($data, $user_email);
    }

    private function sendMailCheckin($assigned_user, $tool_name)
    {
        $user_email = $assigned_user->email;
        $user_name = $assigned_user->first_name . ' ' . $assigned_user->last_name;
        $current_time = Carbon::now();
        $data = [
            'user_name' => $user_name,
            'tool_name' => $tool_name,
            'count' => 1,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendCheckinMailTool::dispatch($data, $user_email);
    }

    private function sendMailConfirm($assigned_user, $tool_name, $is_confirm, $subject, $reason = "")
    {
        $it_ncc_email = Setting::first()->admin_cc_email;
        $user_name = $assigned_user->first_name . ' ' . $assigned_user->last_name;
        $current_time = Carbon::now();
        $data = [
            'user_name' => $user_name,
            'tool_name' => $tool_name,
            'tools_count' => 1,
            'is_confirm' => $is_confirm,
            'subject' => $subject,
            'reason' => $reason,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendConfirmMailTool::dispatch($data, $it_ncc_email);
    }
}
