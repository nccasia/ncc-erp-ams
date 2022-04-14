<?php

namespace App\Domains\Finfast\Models\Traits\Scope;

use App\Domains\Finfast\Models\FinfastSetting;

/**
 * Class AnnouncementScope.
 */
trait FinfastSettingScope
{
    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeEntryIdFilter($query)
    {// todo example
        return $query->where("f_key", FinfastSetting::$EntryFilterKey);
    }
}
