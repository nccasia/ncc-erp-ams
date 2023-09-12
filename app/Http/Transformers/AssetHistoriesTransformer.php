<?php

namespace App\Http\Transformers;
use App\Helpers\Helper;
use App\Models\Actionlog;
use Illuminate\Support\Collection;

class AssetHistoriesTransformer
{
    public function transformAssetHistories(Collection $actionlogs, $total = null)
    {
        $array = [];
        foreach ($actionlogs as $actionlog) {
            $array[] = self::transformAssetHistory($actionlog);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformAssetHistory(Actionlog $actionlog = null)
    {
        $actionType = Helper::replaceSpacesWithUnderscores($actionlog->action_type);
        if ($actionlog) {
            $array = [
                'action_type' => e(trans('admin/reports/general.' . $actionType, [], "vi")),
                'created_at' => Helper::getFormattedDateObject($actionlog->created_at, 'datetime'),
                'user' => e($actionlog->user->fullname),
            ];
            return $array;
        }
    }
}
