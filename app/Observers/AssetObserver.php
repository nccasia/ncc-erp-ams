<?php

namespace App\Observers;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Setting;
use Auth;

class AssetObserver
{
    protected function getActionName($assetOld, $assetNew)
    {
        $action_name = "update";
        //checkout
        if(
            $assetOld['assigned_status'] === config("enum.assigned_status.DEFAULT") &&
            $assetNew['assigned_status'] === config("enum.assigned_status.WAITINGCHECKOUT")
        ) {
            $action_name = 'checkout';
        }

        if(
            $assetOld['assigned_status'] === config("enum.assigned_status.WAITINGCHECKOUT") &&
            $assetNew['assigned_status'] === config("enum.assigned_status.ACCEPT")
        ) {
            $action_name = 'checkout accepted';
        }

        if(
            $assetOld['assigned_status'] === config("enum.assigned_status.WAITINGCHECKOUT") &&
            $assetNew['assigned_status'] === config("enum.assigned_status.DEFAULT")
        ) {
            $action_name = 'checkout rejected';
        }

        //checkin
        if(
            $assetNew['assigned_status'] === config("enum.assigned_status.ACCEPT") &&
            !is_null($assetNew['withdraw_from'])
        ) {
            $action_name = 'checkin rejected';
        }

        if(
            is_null($assetNew['withdraw_from']) &&
            !is_null($assetOld['withdraw_from'])
        ) {
            $action_name = 'checkin accepted';
        }

        if(
            $assetOld['assigned_status'] === config("enum.assigned_status.ACCEPT") &&
            $assetNew['assigned_status'] === config("enum.assigned_status.WAITINGCHECKIN")
        ) {
            $action_name = 'checkin from';
        }

        return $action_name;
    }
    /**
     * Listen to the User created event.
     *
     * @param  Asset  $asset
     * @return void
     */
    public function updating(Asset $asset)
    {
        // If the asset isn't being checked out or audited, log the update.
        // (Those other actions already create log entries.)
        $changed = [];
        $asset->next_audit_date = null;
        foreach ($asset->getOriginal() as $key => $value) {
            if (
                $asset->getOriginal()[$key] != $asset->getAttributes()[$key] ||
                $key === 'withdraw_from' ||
                $key === 'assigned_to'
            ) {
                $changed[$key]['old'] = $asset->getOriginal()[$key];
                $changed[$key]['new'] = $asset->getAttributes()[$key];
            }
        }

        $logAction = new Actionlog();
        $logAction->item_type = Asset::class;
        $logAction->item_id = $asset->id;
        $logAction->created_at = date('Y-m-d H:i:s');
        $logAction->user_id = Auth::id();
        $logAction->log_meta = json_encode($changed);
        $action = $this->getActionName($asset->getOriginal(), $asset->getAttributes());
        if($action === 'checkout') {
            $logAction->target_id = $asset->getAttributes()['assigned_to'];
            $logAction->target_type = 'App\Models\User';
        }
        $logAction->logaction($action);
    }

    /**
     * Listen to the Asset created event, and increment
     * the next_auto_tag_base value in the settings table when i
     * a new asset is created.
     *
     * @param  Asset  $asset
     * @return void
     */
    public function created(Asset $asset)
    {
        if ($settings = Setting::getSettings()) {
            $settings->increment('next_auto_tag_base');
            $settings->save();
        }

        $logAction = new Actionlog();
        $logAction->item_type = Asset::class;
        $logAction->item_id = $asset->id;
        $logAction->created_at = date('Y-m-d H:i:s');
        $logAction->user_id = Auth::id();
        $logAction->logaction('create');
    }

    /**
     * Listen to the Asset deleting event.
     *
     * @param  Asset  $asset
     * @return void
     */
    public function deleting(Asset $asset)
    {
        $logAction = new Actionlog();
        $logAction->item_type = Asset::class;
        $logAction->item_id = $asset->id;
        $logAction->created_at = date('Y-m-d H:i:s');
        $logAction->user_id = Auth::id();
        $logAction->logaction('delete');
    }
}
