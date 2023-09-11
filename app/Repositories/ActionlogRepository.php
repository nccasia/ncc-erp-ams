<?php

namespace App\Repositories;

use App\Models\Actionlog;
use App\Models\Asset;

class ActionlogRepository
{
    protected $actionlog;

    public function __construct(Actionlog $actionlog)
    {
        $this->actionlog = $actionlog;
    }

    public function getAssetHistory($asset_id)
    {
        return
            $this->actionlog::select([
                'action_logs.created_at',
                'action_logs.action_type',
                'action_logs.log_meta',
            ])
            ->where('item_type', '=', Asset::class)
            ->where('item_id', '=', $asset_id)
            ->where(function ($query) {
                $query->where('action_type', '=', config("enum.assigned_status_log.CHECKOUT_ACCEPTED"))
                    ->orWhere('action_type', '=', config("enum.assigned_status_log.CHECKIN_ACCEPTED"));
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}
