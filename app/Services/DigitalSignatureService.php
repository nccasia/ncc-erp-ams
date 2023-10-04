<?php

namespace App\Services;

use App\Exceptions\TaskReturnError;
use App\Jobs\SendCheckinMailDigitalSignature;
use App\Jobs\SendCheckoutMailDigitalSignature;
use App\Jobs\SendConfirmCheckinMail;
use App\Jobs\SendConfirmCheckoutMail;
use App\Jobs\SendRejectCheckinMail;
use App\Jobs\SendRejectCheckoutMail;
use App\Models\Setting;
use App\Repositories\DigitalSignatureRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Http\Response;

class DigitalSignatureService
{
    private $digitalSignatureRepository;
    private $userRepository;

    public function __construct(
        DigitalSignatureRepository $digitalSignatureRepository,
        UserRepository $userRepository
    ) {
        $this->digitalSignatureRepository = $digitalSignatureRepository;
        $this->userRepository = $userRepository;
    }

    public function index($request)
    {
        return $this->digitalSignatureRepository->index($request);
    }

    public function getTotalDetail($request)
    {
        return $this->digitalSignatureRepository->getTotalDetail($request);
    }

    public function assign($request)
    {
        return $this->digitalSignatureRepository->assign($request);
    }

    public function store($request)
    {
        return $this->digitalSignatureRepository->store($request);
    }

    public function show($id)
    {
        return $this->digitalSignatureRepository->show($id);
    }

    public function update($request, $id)
    {
        $digitalSignature = $this->digitalSignatureRepository->getDigitalSignatureById($id);
        $origin_assigned_status = $digitalSignature->assigned_status;
        $request_assigned_status = $request['assigned_status'];
        $request_reason = $request['reason'] ?? "";
        $update_type = null;
        $user = null;
        if ($digitalSignature->assigned_to) {
            $user = $this->userRepository->getUserById($digitalSignature->assigned_to);
        }

        if (
            !$user
            || !$request['assigned_status']
            || $origin_assigned_status === $request_assigned_status
        ) {
            return $this->digitalSignatureRepository->update($request, $id, config('enum.update_type.DEFAULT'));
        }

        if ($request_assigned_status == config('enum.assigned_status.ACCEPT')) {
            if ($digitalSignature->withdraw_from) {
                $update_type = config('enum.update_type.ACCEPT_CHECKIN');
            } else {
                $update_type = config('enum.update_type.ACCEPT_CHECKOUT');
            }
        } elseif ($request_assigned_status == config('enum.assigned_status.REJECT')) {
            if ($digitalSignature->withdraw_from) {
                $update_type = config('enum.update_type.REJECT_CHECKIN');
            } else {
                $update_type = config('enum.update_type.REJECT_CHECKOUT');
            }
        }
        $this->sendMail($user, $digitalSignature, $update_type, $request_reason);
        return $this->digitalSignatureRepository->update($request, $id, $update_type);
    }

    public function checkout($request, $digital_signature_id)
    {
        $digitalSignature = $this->digitalSignatureRepository->getDigitalSignatureById($digital_signature_id);
        if (!$digitalSignature->availableForCheckout()) {
            throw new TaskReturnError(
                'error',
                ["digital_signature" => $digitalSignature->seri],
                trans('admin/digital_signatures/message.checkout.not_available'),
                Response::HTTP_BAD_REQUEST
            );
        }
        $target = $this->userRepository->getUserById($request['assigned_to']);
        $note = $request['note'] ? $request['note'] : null;
        $checkout_date = $request['checkout_date'];
        $digitalSignature->status_id = config('enum.status_id.ASSIGN');
        if (!$digitalSignature->checkOut($target, $checkout_date, $note, $digitalSignature->name, config('enum.assigned_status.WAITINGCHECKOUT'))) {
            throw new TaskReturnError(
                'error',
                null,
                trans('admin/digital_signatures/message.checkout.error'),
                Response::HTTP_BAD_REQUEST
            );
        }
        $this->sendMail($target, $digitalSignature, config("enum.mail_type.CHECKOUT"));
        return ['digital_signature' => $digitalSignature->seri];
    }

    public function checkin($request, $signature_id)
    {
        $signature = $this->digitalSignatureRepository->getDigitalSignatureById($signature_id);
        if (is_null($target = $signature->assigned_to)) {
            throw new TaskReturnError(
                'error',
                ['signature' => e($signature->seri)],
                trans('admin/digital_signatures/message.checkin.already_checked_in'),
                Response::HTTP_BAD_REQUEST
            );
        }
        if (!$signature->availableForCheckin()) {
            throw new TaskReturnError(
                'error',
                ['signature' => e($signature->seri)],
                trans('admin/digital_signatures/message.checkin.not_available'),
                Response::HTTP_BAD_REQUEST
            );
        }

        $checkin_date = $request['checkin_at'] ?? "";
        $note = $request['notes'] ?? "";
        $target = $this->userRepository->getUserById($signature->assigned_to);

        if (!$signature->checkIn($target, $checkin_date, $note, $signature->name, config('enum.assigned_status.WAITINGCHECKIN'))) {
            throw new TaskReturnError(
                'error',
                null,
                trans('admin/digital_signatures/message.checkin.error'),
                Response::HTTP_BAD_REQUEST
            );
        }
        $this->sendMail($target, $signature, config("enum.mail_type.CHECKIN"));
        return ['digital_signature' => e($signature->seri)];
    }

    public function delete($id)
    {
        return $this->digitalSignatureRepository->delete($id);
    }

    public function sendMail($user, $signature, $type, $request_reason = "")
    {
        $data = [
            'user_name' => $user->full_name,
            'signature_name' => $signature->name,
            'seri' => $signature->seri,
            'signatures_count' => 1,
            'count' => 1,
            'location_address' => null,
            'time' => Carbon::now()->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
            'reason' => '',
            'is_confirm' => '',
        ];
        $it_ncc_email = Setting::first()->admin_cc_email;
        $mail_class = null;
        $mail_sent = null;
        switch ($type) {
            case config("enum.mail_type.CHECKIN"):
                $mail_sent = $user->email;
                $mail_class = SendCheckinMailDigitalSignature::class;
                break;
            case config("enum.mail_type.CHECKOUT"):
                $mail_sent = $user->email;
                $mail_class = SendCheckoutMailDigitalSignature::class;
                break;
            case config('enum.update_type.ACCEPT_CHECKIN'):
                $data['is_confirm'] = 'đã xác nhận thu hồi';
                $mail_sent = $it_ncc_email;
                $mail_class = SendConfirmCheckinMail::class;
                break;
            case config('enum.update_type.ACCEPT_CHECKOUT'):
                $data['is_confirm'] = 'đã xác nhận cấp phát';
                $mail_sent = $it_ncc_email;
                $mail_class = SendConfirmCheckoutMail::class;
                break;
            case config('enum.update_type.REJECT_CHECKIN'):
                $data['is_confirm'] = 'đã từ chối thu hồi';
                $data['reason'] = 'Lý do: ' . $request_reason;
                $mail_sent = $it_ncc_email;
                $mail_class = SendRejectCheckinMail::class;
                break;
            case config("enum.update_type.REJECT_CHECKOUT"):
                $data['is_confirm'] = 'đã từ chối nhận';
                $data['reason'] = 'Lý do: ' . $request_reason;
                $mail_sent = $it_ncc_email;
                $mail_class = SendRejectCheckoutMail::class;
                break;
        }

        $mail_class::dispatch($data, $mail_sent);
    }
}
