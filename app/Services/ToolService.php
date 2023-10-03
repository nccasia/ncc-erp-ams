<?php

namespace App\Services;

use App\Exceptions\TaskReturnError;
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
                $update_type = config('enum.update_type.ACCEPT_CHECKIN');
            } else {
                $update_type = config('enum.update_type.ACCEPT_CHECKOUT');
            }
        } elseif ($request_assigned_status == config('enum.assigned_status.REJECT')) {
            if ($tool->withdraw_from) {
                $update_type = config('enum.update_type.REJECT_CHECKIN');
            } else {
                $update_type = config('enum.update_type.REJECT_CHECKOUT');
            }
        }
        $this->sendMail($user,$tool,$update_type,$request_reason);
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
            throw new TaskReturnError(
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

            throw new TaskReturnError(
                'error',
                null,
                $tool->getErrors(),
                Response::HTTP_BAD_REQUEST
            );
        }
        $this->sendMail($target, $tool, config("enum.mail_type.CHECKOUT"));
        return ['tool' => e($tool->name)];
    }

    public function checkin($request, $tool_id)
    {
        $tool = $this->toolRepository->getToolOrFailById($tool_id);
        if (is_null($target = $tool->assigned_to)) {
            throw new TaskReturnError(
                'error',
                ['name' => e($tool->name)],
                trans('admin/tools/message.checkin.already_checked_in'),
                Response::HTTP_BAD_REQUEST
            );
        }
        if (!$tool->availableForCheckin()) {
            throw new TaskReturnError(
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
            throw new TaskReturnError(
                'error',
                null,
                $tool->getErrors(),
                Response::HTTP_BAD_REQUEST
            );
        }
        $this->sendMail($target, $tool, config("enum.mail_type.CHECKIN"));
        return ['tool' => e($tool->name)];
    }

    public function getToolById($id)
    {
        return $this->toolRepository->getToolById($id);
    }

    public function sendMail($user, $tool, $type, $request_reason = "")
    {
        $data = [
            'user_name' => $user->full_name,
            'tool_name' => $tool->name,
            'count' => 1,
            'time' => Carbon::now()->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
            'is_confirm' => "",
            'subject' => "",
            'reason' => ""
        ];
        $it_ncc_email = Setting::first()->admin_cc_email;
        $mail_class = null;
        $mail_sent = null;
        switch ($type) {
            case config("enum.mail_type.CHECKIN"):
                $mail_sent = $user->email;
                $mail_class = SendCheckinMailTool::class;
                break;
            case config("enum.mail_type.CHECKOUT"):
                $mail_sent = $user->email;
                $mail_class = SendCheckoutMailTool::class;
                break;
            case config('enum.update_type.ACCEPT_CHECKIN'):
                $data["is_confirm"] = 'đã xác nhận thu hồi';
                $data["subject"] = 'Mail xác nhận thu hồi tool';
                $mail_sent = $it_ncc_email;
                $mail_class = SendConfirmMailTool::class;
            case config('enum.update_type.ACCEPT_CHECKOUT'):
                $data["is_confirm"] = 'đã xác nhận cấp phát';
                $data["subject"] = 'Mail xác nhận cấp phát tool';
                $mail_sent = $it_ncc_email;
                $mail_class = SendConfirmMailTool::class;
            case config('enum.update_type.REJECT_CHECKIN'):
                $data['is_confirm'] = 'đã từ chối thu hồi';
                $data["subject"] = 'Mail từ chối thu hồi tool';
                $data['reason'] = 'Lý do: ' . $request_reason;
                $mail_sent = $it_ncc_email;
                $mail_class = SendConfirmMailTool::class;
            case config("enum.update_type.REJECT_CHECKOUT"):
                $data['is_confirm'] = 'đã từ chối nhận';
                $data["subject"] = 'Mail từ chối nhận tool';
                $data['reason'] = 'Lý do: ' . $request_reason;
                $mail_sent = $it_ncc_email;
                $mail_class = SendConfirmMailTool::class;
        }

        $mail_class::dispatch($data, $mail_sent);
    }
}
