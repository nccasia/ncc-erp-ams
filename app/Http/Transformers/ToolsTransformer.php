<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Collection;

class ToolsTransformer
{
    public function transformTools(Collection $tools, $total)
    {
        $array = [];
        foreach ($tools as $tool) {
            $array[] = self::transformTool($tool);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformTool(Tool $tool)
    {
        $array = [
            'id' => (int) $tool->id,
            'name' => e($tool->name),
            'purchase_cost' => Helper::formatCurrencyOutput($tool->purchase_cost),
            'version' => e($tool->version),
            'user' =>  ['id' => (int) $tool->user_id, 'name'=> e($tool->username)],
            'manufacturer' =>  ($tool->manufacturer) ? [
                'id' => (int) $tool->manufacturer->id, 
                'name'=> e($tool->manufacturer->name),
                ] : null,
            'category' =>  ($tool->category) ? [
                'id' => (int) $tool->category->id, 
                'name'=> e($tool->category->name),
                'category_type'=> e($tool->category->category_type)
                ] : null,
            'checkout_count' => (int) $tool->tools_users_count,
            'checkout_at' =>  Helper::getFormattedDateObject($tool->checkout_at, 'datetime'),
            'notes' => e($tool->notes),
            'user_can_checkout'=> !$tool->deleted_at,
            'purchase_date' => Helper::getFormattedDateObject($tool->purchase_date, 'date'),
            'created_at' => Helper::getFormattedDateObject($tool->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($tool->updated_at, 'datetime'),
        ];

        return $array;
    }

    public function transformAssetsDatatable($tools)
    {
        return (new DatatablesTransformer)->transformDatatables($tools);
    }
}
