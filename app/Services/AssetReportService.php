<?php

namespace App\Services;

use App\Repositories\ActionlogRepository;
use App\Repositories\UserRepository;

class AssetReportService
{
    protected $actionlogRepository;
    protected $userRepository;

    public function __construct(ActionlogRepository $actionlogRepository, UserRepository $userRepository)
    {
        $this->actionlogRepository = $actionlogRepository;
        $this->userRepository = $userRepository;
    }

    protected function getUser($assetHistories)
    {
        $result = [];
        foreach ($assetHistories as $assetHistory) {
            $assetHistoryTemp = $assetHistory;
            $log_meta = json_decode($assetHistory['log_meta']);

            if ($log_meta) {
                switch ($assetHistory['action_type']) {
                    case config("enum.log_status.CHECKOUT_ACCEPTED"):
                        if (!is_null($log_meta->assigned_to)) {
                            $user_id = $log_meta->assigned_to->new;
                        }
                        break;

                    case config("enum.log_status.CHECKIN_ACCEPTED"):
                        if (!is_null($log_meta->withdraw_from)) {
                            $user_id = $log_meta->withdraw_from->old;
                        }
                        break;
                }

                $user = $this->userRepository->findUserWithTrash($user_id);
                $assetHistoryTemp['user'] = $user;
            }

            $result[] = $assetHistoryTemp;
        }
        return $result;
    }

    public function getAssetHistory($asset_id)
    {
        $assetHistories = $this->actionlogRepository->getAssetHistory($asset_id);
        return collect($this->getUser($assetHistories));
    }
}
