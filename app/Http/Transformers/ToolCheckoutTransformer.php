<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\ToolUser;
use Illuminate\Database\Eloquent\Collection;

class ToolCheckoutTransformer
{
    public function transformToolsCheckout(Collection $tools_users, $total)
    {
        $array = [];
        foreach ($tools_users as $tool_user) {
            $array[] = self::transformTool($tool_user);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformTool(ToolUser $tool_user)
    {
        $array = [
            'id' => (int) $tool_user->id,
            'tool_id' => (int) $tool_user->tool->id,
            'name' => e($tool_user->tool->name),
            'purchase_cost' => Helper::formatCurrencyOutput($tool_user->tool->purchase_cost),
            'version' => e($tool_user->tool->version),
            'assigned_to' =>  ($tool_user->user) ? [
                'id' => (int) $tool_user->user->id, 
                'name'=> e($tool_user->user->username),
            ] : null,
            'manufacturer' =>  ($tool_user->tool->manufacturer) ? [
                'id' => (int) $tool_user->tool->manufacturer->id, 
                'name'=> e($tool_user->tool->manufacturer->name),
                ] : null,
            'category' =>  ($tool_user->tool->category) ? [
                'id' => (int) $tool_user->tool->category->id, 
                'name'=> e($tool_user->tool->category->name),
                'category_type'=> e($tool_user->tool->category->category_type)
                ] : null,
            'notes' => e($tool_user->notes),
            'user_can_checkin'=> $tool_user->tool->availableForCheckin($tool_user->user->id),
            'checkout_at' => Helper::getFormattedDateObject($tool_user->checkout_at, 'datetime'),
            'purchase_date' => Helper::getFormattedDateObject($tool_user->tool->purchase_date, 'date'),
            'created_at' => Helper::getFormattedDateObject($tool_user->tool->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($tool_user->tool->updated_at, 'datetime'),
        ];

        return $array;
    }

    public function transformAssetsDatatable($tools)
    {
        return (new DatatablesTransformer)->transformDatatables($tools);
    }
}
