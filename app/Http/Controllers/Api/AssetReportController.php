<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\AssetHistoriesTransformer;
use App\Models\Actionlog;
use App\Models\User;
use Illuminate\Http\Request;

class AssetReportController extends Controller
{
    protected function getUser($assetHistories)
    {
        $rs = [];
        foreach ($assetHistories as $assetHistory) {
            $temp = $assetHistory;
            $log_meta = json_decode($assetHistory['log_meta']);
            if ($log_meta) {
                switch ($assetHistory['action_type']) {
                    case 'checkout accepted':
                        $user_id = $log_meta->assigned_to->new;
                        break;
                    case 'checkin accepted':
                        $user_id = $log_meta->withdraw_from->old;
                        break;
                }
                $user = User::withTrashed()->where('id', '=', $user_id)->first();
                $temp['user'] = $user;
            }
            $rs [] = $temp;
        }
        return $rs;
    }
    public function getAssetHistory($asset_id)
    {
        $assetHistories = Actionlog::select([
            'action_logs.created_at',
            'action_logs.action_type',
            'action_logs.log_meta',
        ])
            ->where('item_type', '=', 'App\Models\Asset')
            ->where('item_id', '=', $asset_id)
            ->where(function ($query) {
                $query->where('action_type', '=', 'checkout accepted')
                    ->orWhere('action_type', '=', 'checkin accepted');
            })
            ->orderBy('id', 'desc')
            ->get();

        $assetHistories = collect($this->getUser($assetHistories));
        return (new AssetHistoriesTransformer)->transformAssetHistories($assetHistories);
    }
}
