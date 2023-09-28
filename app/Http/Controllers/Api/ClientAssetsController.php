<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\AssetsTransformer;
use App\Services\ClientAssetService;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetCheckoutRequest;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\CustomField;
use App\Models\Setting;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Requests\ImageUploadRequest;
use Illuminate\Support\Arr;
use Route;

class ClientAssetsController extends Controller
{
    private $clientAssetService;

    public function __construct(ClientAssetService $clientAssetService)
    {
        $this->clientAssetService = $clientAssetService;
    }

    public function index(Request $request)
    {
        $this->authorize('index', Asset::class);
        $result = $this->clientAssetService->getListAssets($request->all());
        return (new AssetsTransformer)->transformAssets($result['assets'], $result['total']);
    }

    public function getTotalDetail(Request $request)
    {
        $this->authorize('index', Asset::class);
        $response = $this->clientAssetService->getTotalDetail($request->all());

        if ($request->has('IS_EXPIRE_PAGE') && $request->get('IS_EXPIRE_PAGE')) {
            $expire_asset = $this->assetExpiration($request);
        }

        if (isset($expire_asset)) {
            $response = $this->clientAssetService->getTotalDetailExpire($expire_asset);
        }

        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                $response,
                null
            )
        );
    }

    public function assetExpiration(Request $request)
    {
        $this->authorize('index', Asset::class);
        $result = $this->clientAssetService->getListAssets($request->all());

        $expiration = Carbon::now()->addDays(30)->startOfDay()->toDateTimeString();

        $data = [];
        $data['total'] = 0;
        $assets =  (new AssetsTransformer)->transformAssets($result['assets'], $result['total']);

        foreach ($assets['rows'] as $asset) {
            if (!$asset['warranty_expires']) continue;
            if ((new Carbon($asset['warranty_expires']['date']))->lte($expiration)) {
                $data['rows'][] = $asset;
                $data['total'] += 1;
            }
        }
        return $data;
    }

    public function store(ImageUploadRequest $request)
    {
        $this->authorize('create', Asset::class);
        $asset = $this->clientAssetService->store($request->all());

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $asset,
            trans('admin/hardware/message.create.success')
        ));
    }

    public function update(ImageUploadRequest $request, $id)
    {
        $this->authorize('update', Asset::class);
        $asset = $this->clientAssetService->update($request->all(), $id);

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $asset,
            trans('admin/hardware/message.update.success')
        ));
    }

    public function multiUpdate(ImageUploadRequest $request)
    {
        $this->authorize('update', Asset::class);
        $assets = $this->clientAssetService->update($request->all());

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $assets,
            trans('admin/hardware/message.update.success')
        ));
    }

    public function destroy($id)
    {
        $this->authorize('delete', Asset::class);
        $this->clientAssetService->destroy($id);

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            null,
            trans('admin/hardware/message.delete.success')
        ));
    }

    public function multiCheckout(AssetCheckoutRequest $request)
    {
        $this->authorize('checkout', Asset::class);
        $result = $this->clientAssetService->checkout($request->all());

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $result['payload'],
            trans('admin/hardware/message.checkout.success')
        ));
    }

    public function multiCheckin(Request $request)
    {
        $this->authorize('checkin', Asset::class);
        $result = $this->clientAssetService->checkin($request->all());

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $result['payload'],
            trans('admin/hardware/message.checkin.success')
        ));
    }

    public function checkin(Request $request, $asset_id)
    {
        $this->authorize('checkin', Asset::class);
        $result = $this->clientAssetService->checkin($request->all(), $asset_id);

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $result['payload'],
            trans('admin/hardware/message.checkin.success')
        ));
    }

    public function checkout(AssetCheckoutRequest $request, $asset_id)
    {
        $this->authorize('checkout', Asset::class);

        $result = $this->clientAssetService->checkout($request->all(), $asset_id);

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            $result['payload'],
            trans('admin/hardware/message.checkout.success')
        ));
    }
}
