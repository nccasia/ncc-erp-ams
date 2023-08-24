<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use Illuminate\Support\Collection;

class W2Transformer
{
    public function transformRequests(Collection $requests)
    {
        $array = [];
        foreach ($requests as $request) {
            $array[] = self::transformRequest($request);
        }

        return (new DatatablesTransformer)->transformDatatables($array);
    }

    public function transformRequest($request = null)
    {
        if ($request) {
            $array = [
                "id" => e($request->id),
                "type" => e($request->workflowDefinitionDisplayName),
                "userRequestName" => e($request->userRequestName),
                "status" => e($request->status),
                "createdAt" => Helper::getFormattedDateObject($request->createdAt, 'datetime'),
                "lastExecutedAt" => Helper::getFormattedDateObject($request->lastExecutedAt, 'datetime')
            ];

            return $array;
        }
    }
}
