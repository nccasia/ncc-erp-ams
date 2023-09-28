<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Jobs\SendCheckinMail;
use App\Jobs\SendCheckoutMail;
use App\Repositories\AssetHistoryDetailRepository;
use App\Repositories\AssetHistoryRepository;
use App\Repositories\AssetRepository;
use App\Repositories\LocationRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class ClientAssetService
{
    private $assetRepository;
    private $assetHistoryRepository;
    private $assetHistoryDetailRepository;
    private $userRepository;
    private $locationRepository;

    public function __construct(
        AssetRepository $assetRepository,
        AssetHistoryRepository $assetHistoryRepository,
        AssetHistoryDetailRepository $assetHistoryDetailRepository,
        UserRepository $userRepository,
        LocationRepository $locationRepository
    ) {
        $this->assetRepository = $assetRepository;
        $this->assetHistoryDetailRepository = $assetHistoryDetailRepository;
        $this->assetHistoryRepository = $assetHistoryRepository;
        $this->userRepository = $userRepository;
        $this->locationRepository = $locationRepository;
    }

    public function getListAssets(array $data)
    {
        return $this->assetRepository->getListAssets($data, true);
    }

    public function getTotalDetail(array $data)
    {
        $total_asset_by_model = $this->assetRepository->getTotalDetail($data, true);

        $total_detail = $total_asset_by_model->map(function ($value, $key) {
            return [
                'name' => $key,
                'total' => $value
            ];
        })->values()->toArray();

        return $total_detail;
    }

    public function store(array $data)
    {
        return $this->assetRepository->store($data, true);
    }

    public function update(array $data, $id = null)
    {
        return $this->assetRepository->update($data, $id, true);
    }

    public function destroy($id)
    {
        return $this->assetRepository->destroy($id);
    }

    public function checkin(array $data, $assetId = null)
    {
        $status = "error";
        $assets = $data['assets'] ?? [$assetId];
        $asset_tag = null;
        $asset = $this->assetRepository->getAssetById($assets);
        $listAsset = [];
        $item = 0;

        if (!$asset) {
            return Helper::formatStandardApiResponse(
                'error',
                null,
                trans('admin/hardware/message.does_not_exist')
            );
        }

        foreach ($asset as $value) {
            $item++;
            $listAsset[$value['assignedTo']['id']][$item] = $value;
        }

        if (!is_array($listAsset) || !isset($listAsset) || count($listAsset) === 0) {
            return Helper::formatStandardApiResponse('error');
        }

        foreach ($listAsset as $userId => $assets) {
            $countSuccess = 0;
            $asset_tag = null;
            $asset_name = null;
            foreach ($assets as $asset) {

                if (is_null($target = $asset->assignedTo)) {
                    return Helper::formatStandardApiResponse(
                        'error',
                        ['asset' => e($asset->asset_tag)],
                        trans('admin/hardware/message.checkin.already_checked_in')
                    );
                }

                $checkin_at = null;
                $note = $data['note'] ?? null;
                $countAssets = count($assets);

                if (Arr::exists($data, 'checkin_at')) {
                    $checkin_at = $data['checkin_at'];
                }

                if (Arr::exists($data, 'status_id')) {
                    if ($data['status_id'] == config('enum.status_id.READY_TO_DEPLOY')) {
                        $asset->status_id = config('enum.status_id.ASSIGN');
                    } else {
                        $asset->status_id = $data['status_id'];
                    }
                }

                if ($asset === end($assets)) {
                    $asset_name .= $asset->name;
                    $asset_tag .= $asset->asset_tag;
                } else {
                    $asset_name .= $asset->name . ", ";
                    $asset_tag .= $asset->asset_tag . ", ";
                }

                if ($asset->checkIn($target, Auth::user(), $checkin_at, $asset->status_id, $note, $asset->name, config('enum.assigned_status.WAITINGCHECKIN'))) {
                    $this->saveAssetHistory($asset->id, config('enum.asset_history.CHECK_IN_TYPE'));
                    $countSuccess++;
                }
            }
            if ($countSuccess == count($assets)) {
                $dataUser = $this->setDataUser($userId, $asset_name, $countAssets);
                SendCheckinMail::dispatch($dataUser, $dataUser['user_email']);
                $status = "success";
            }
        }

        return Helper::formatStandardApiResponse(
            $status,
            ['asset' => e($asset_tag)],
        );
    }

    public function checkout(array $data, $assetId = null)
    {
        $assets = $data['assets'] ?? [$assetId];
        $asset_name = null;
        $asset_tag  = null;
        $status = "error";
        $countSuccess = 0;

        foreach ($assets as $asset_id) {
            $asset = $this->assetRepository->getAssetById($asset_id);

            if (!$asset) {
                return Helper::formatStandardApiResponse(
                    'error',
                    null,
                    trans('admin/hardware/message.does_not_exist')
                );
            }

            if (!$asset->availableForCheckout()) {
                return Helper::formatStandardApiResponse([
                    'error',
                    ['asset' => e($asset->asset_tag)],
                    trans('admin/hardware/message.checkout.not_available')
                ]);
            }

            $error_payload = [];
            $error_payload['asset'] = [
                'id' => $asset->id,
                'asset_tag' => $asset->asset_tag,
            ];

            if ($data['checkout_to_type'] == 'user') {
                if (Arr::exists($data, 'assigned_user')) {
                    $target = $this->userRepository->getUserById($data['assigned_user']);
                }
                $asset->location_id = (($target) && (isset($target->location_id))) ? $target->location_id : '';
                $error_payload['target_id'] = $data['assigned_user'];
                $error_payload['target_type'] = 'user';
            }

            if (!isset($target)) {
                return Helper::formatStandardApiResponse(
                    'error',
                    $error_payload,
                    'Checkout target for asset ' . e($asset->asset_tag) . ' is invalid - ' . $error_payload['target_type'] . ' does not exist.'
                );
            }

            $checkout_at = $data['checkout_at'];
            $expected_checkin = $data['expected_checkin'] ?? null;
            $note = $data['note'] ?? null;
            $asset->status_id = config('enum.status_id.ASSIGN');

            if ($asset_id === end($assets)) {
                $asset_name .= $asset->name;
                $asset_tag .= $asset->asset_tag;
            } else {
                $asset_name .= $asset->name . ", ";
                $asset_tag .= $asset->asset_tag . ", ";
            }

            if ($asset->checkOut($target, Auth::user(), $checkout_at, $expected_checkin, $note, $asset->name, $asset->location_id, config('enum.assigned_status.WAITINGCHECKOUT'))) {
                $this->saveAssetHistory($asset_id, config('enum.asset_history.CHECK_OUT_TYPE'));
                $countSuccess++;
            }
        }


        if ($countSuccess === count($assets)) {
            $status = "success";
            $dataUser = $this->setDataUser($data['assigned_user'], $asset->name, count($assets), true);
            SendCheckoutMail::dispatch($dataUser, $dataUser['user_email']);
        }

        return Helper::formatStandardApiResponse(
            $status,
            ['asset' => e($asset_tag)],
        );
    }

    private function saveAssetHistory($asset_id, $type)
    {
        $asset = $this->assetRepository->getAssetById($asset_id);

        $history = $this->assetHistoryRepository->store([
            'type' => $type,
            'assigned_to' => $asset->assigned_to,
            'user_id' => $asset->user_id,
        ]);

        $this->assetHistoryDetailRepository->store([
            'history_id' => $history->id,
            'asset_id'   => $asset_id,
        ]);
    }

    private function setDataUser($userId, $asset_name, $countAssets, $is_checkout = false)
    {
        $user = $this->userRepository->getUserById($userId);
        $user_email = $user->email;
        $user_name  = $user->getFullNameAttribute();
        $current_time = Carbon::now();

        $location_id = $user->location_id ?? env('DEFAULT_LOCATION_USER');
        $location = $this->locationRepository->getLocationById($location_id);
        $location_address = ($is_checkout) ? null : $location->name;
        $location_address = $this->formatLocationAddress($location, $location_address);

        $data = [
            'user_name' => $user_name,
            'asset_name' => $asset_name,
            'count' => $countAssets,
            'user_email' => $user_email,
            'location_address' => $location_address,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];

        return $data;
    }

    private function formatLocationAddress($location, $location_address = null)
    {
        $location_arr = array();

        if (!is_null($location)) {
            if (!is_null($location->address2)) {
                array_push($location_arr, $location->address2);
            }

            if (!is_null($location->address)) {
                array_push($location_arr, $location->address);
            }
            if (!is_null($location->state)) {
                array_push($location_arr, $location->state);
            }

            if (!is_null($location->city)) {
                array_push($location_arr, $location->city);
            }
        }

        foreach ($location_arr as $value) {
            if ($value === end($location_arr)) {
                $location_address .= $value . '.';
            } else {
                $location_address .= ' ' . $value . ', ';
            }
        }

        return $location_address;
    }
}
