<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\DigitalSignaturesTransformer;
use App\Models\DigitalSignatures;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class DigitalSignaturesController extends Controller
{
    private $digitalSignatureService;
    public function __construct(
        DigitalSignatureService $digitalSignatureService
    ) {
        $this->digitalSignatureService = $digitalSignatureService;
    }
    public function index(request $request)
    {
        $this->authorize('view', DigitalSignatures::class);
        $data = $this->digitalSignatureService->index($request->all());
        return (new DigitalSignaturesTransformer())->transformSignatures($data['digital_signatures'], $data['total']);
    }

    public function getTotalDetail(request $request)
    {
        $this->authorize('view', DigitalSignatures::class);
        $data = $this->digitalSignatureService->getTotalDetail($request->all());
        return response()->json(Helper::formatStandardApiResponse($data['status'], $data['payload'], $data['message']));
    }

    public function store(Request $request)
    {
        $this->authorize('create', DigitalSignatures::class);
        try {
            $data = $this->digitalSignatureService->store($request->all());
            return response()->json(Helper::formatStandardApiResponse(
                'success',
                $data,
                trans('admin/digital_signatures/message.create.success')
            ), Response::HTTP_OK);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function show(int $id)
    {
        $this->authorize('view', DigitalSignatures::class);
        $data = $this->digitalSignatureService->show($id);

        return (new DigitalSignaturesTransformer())->transformSignature($data);
    }

    public function update(Request $request, int $id)
    {
        $this->authorize('update', DigitalSignatures::class);
        $data = $this->digitalSignatureService->update($request->all(), $id);

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $data,
            trans('admin/digital_signatures/message.update.success')
        ), Response::HTTP_OK);
    }

    public function destroy(int $id)
    {
        $this->authorize('delete', DigitalSignatures::class);
        try {
            $data = $this->digitalSignatureService->delete($id);
            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    null,
                    trans('admin/digital_signatures/message.delete.success')
                ),
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function checkout(Request $request, int $digital_signature_id)
    {
        $this->authorize('checkout', DigitalSignatures::class);
        try {
            $data = $this->digitalSignatureService->checkout($request->all(), $digital_signature_id);
            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    $data,
                    trans('admin/digital_signatures/message.checkout.success')
                ),
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function multiCheckout(Request $request)
    {
        $this->authorize('checkout', Asset::class);
        try {
            $digitalSignatures = request('signatures');
            foreach ($digitalSignatures as $digital_signature_id) {
                $data = $this->digitalSignatureService->checkout($request->all(), $digital_signature_id);
            }
            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    null,
                    trans('admin/digital_signatures/message.checkout.success')
                ),
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function checkIn(Request $request, $signature_id)
    {
        $this->authorize('checkin', DigitalSignatures::class);
        try {
            $data = $this->digitalSignatureService->checkin($request->all(), $signature_id);
            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    $data,
                    trans('admin/digital_signatures/message.checkin.success')
                ),
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function multiCheckin(Request $request)
    {
        $this->authorize('checkin', DigitalSignatures::class);
        try {
            $digitalSignatures = request('signatures');
            foreach ($digitalSignatures as $digital_signature_id) {
                $data = $this->digitalSignatureService->checkin($request->all(), $digital_signature_id);
            }
            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    $data,
                    trans('admin/digital_signatures/message.checkin.success')
                ),
                Response::HTTP_OK
            );
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function assign(Request $request)
    {
        $this->authorize('view', Tool::class);
        $data = $this->digitalSignatureService->assign($request->all());
        return (new DigitalSignaturesTransformer())->transformSignatures($data['digital_signatures'], $data['total']);
    }

    public function multiUpdate(Request $request)
    {
        $this->authorize('update', DigitalSignatures::class);

        try {
            $signatures_id = $request->input('tax_tokens');
            foreach ($signatures_id as $id) {
                $data = $this->digitalSignatureService->update($request->all(), $id);
            }
            return response()->json(Helper::formatStandardApiResponse(
                'success',
                $data,
                trans('admin/digital_signatures/message.update.success')
            ), Response::HTTP_OK);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
