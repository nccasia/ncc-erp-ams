<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use Illuminate\Support\Collection;

class W2Transformer
{
    public function transformRequests(Collection $requests, $total = 0)
    {
        $array = [];
        foreach ($requests as $request) {
            $array[] = self::transformRequest($request);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformRequest($request = null)
    {
        if ($request) {
            $array = [
                "id" => $request->id,
                "type" => $request->name ?? "",
                "userRequestName" => $request->authorName ?? "",
                "status" => $request->status ?? 0,
                "createdAt" => Helper::getFormattedDateObject($request->creationTime, 'datetime') ?? "",
                // "lastExecutedAt" => Helper::getFormattedDateObject($request->lastExecutedAt, 'datetime')
            ];

            return $array;
        }
    }
}
