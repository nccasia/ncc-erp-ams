<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Collection;

class ToolsTransformer
{
    public function transformtools(Collection $tool, $total)
    {
        $array = [];
        foreach ($tool as $tool) {
            $array[] = self::transformTool($tool);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformTool(Tool $tool)
    {
        $array = [
            // 'id' => (int) $tool->id,
            // 'name' => e($tool->name),
            // 'purchase_cost' => Helper::formatCurrencyOutput($tool->purchase_cost),
            // 'version' => e($tool->version),
            // 'user' =>  ['id' => (int) $tool->user_id, 'name'=> e($tool->username)],
            // 'manufacturer' =>  ($tool->manufacturer) ? [
            //     'id' => (int) $tool->manufacturer->id, 
            //     'name'=> e($tool->manufacturer->name),
            //     ] : null,
            // 'category' =>  ($tool->category) ? [
            //     'id' => (int) $tool->category->id, 
            //     'name'=> e($tool->category->name),
            //     'category_type'=> e($tool->category->category_type)
            //     ] : null,
            // 'checkout_count' => (int) $tool->tool_users_count,
            // 'checkout_at' =>  Helper::getFormattedDateObject($tool->checkout_at, 'datetime'),
            // 'notes' => e($tool->notes),
            // 'user_can_checkout'=> !$tool->deleted_at,
            // 'purchase_date' => Helper::getFormattedDateObject($tool->purchase_date, 'date'),
            // 'created_at' => Helper::getFormattedDateObject($tool->created_at, 'datetime'),
            // 'updated_at' => Helper::getFormattedDateObject($tool->updated_at, 'datetime'),
            'id' => (int) $tool->id,
            'name' => $tool->name,
            'supplier' => ($tool->supplier) ? [
                'id' => (int) $tool->supplier->id,
                'name' => e($tool->supplier->name),
            ] : null,
            'location' => ($tool->location) ? [
                'id' => (int) $tool->location->id,
                'name' => e($tool->location->name),
            ] : null,
            'category' => ($tool->category) ? [
                'id' => (int) $tool->category->id,
                'name' => e($tool->category->name),
            ] : null,
            'qty' => (int) $tool->qty,
            'assigned_to' => $this->transformAssignedTo($tool),
            'assigned_status' => $tool->assigned_status,
            'withdraw_from' =>  $tool->withdraw_from,
            'purchase_date' => Helper::getFormattedDateObject($tool->purchase_date, 'date'),
            'purchase_cost' => Helper::formatCurrencyOutput($tool->purchase_cost),
            'expiration_date' => Helper::getFormattedDateObject($tool->expiration_date, 'date'),
            'checkout_date' => Helper::getFormattedDateObject($tool->checkout_date, 'datetime'),
            'last_checkout' => Helper::getFormattedDateObject($tool->last_checkout, 'datetime'),
            'checkin_date' => Helper::getFormattedDateObject($tool->checkin_date, 'datetime'),
            'status_label' => ($tool->tokenStatus) ? [
                'id' => (int) $tool->tokenStatus->id,
                'name'=> e($tool->tokenStatus->name),
                'status_type'=> e($tool->tokenStatus->getStatuslabelType()),
                'status_meta' => e($tool->tokenStatus->getStatuslabelType()),
            ] : null,
            'user_can_checkout' => (bool) $tool->availableForCheckout(),
            'user_can_checkin' => (bool) $tool->availableForCheckin(),
            'checkout_counter' => (int) $tool->checkout_counter,
            'checkin_counter' => (int) $tool->checkin_counter,
            'note' => e($tool->note),
            'created_at' => Helper::getFormattedDateObject($tool->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($tool->updated_at, 'datetime'),
        ];

        return $array;
    }

    public function transformAssetsDatatable($tool)
    {
        return (new DatatablesTransformer)->transformDatatables($tool);
    }

    public function transformAssignedTo($tool)
    {
        return $tool->assignedUser ? [
                'id' => (int) $tool->assignedUser->id,
                'username' => e($tool->assignedUser->username),
                'name' => e($tool->assignedUser->getFullNameAttribute()),
                'first_name' => e($tool->assignedUser->first_name),
                'last_name' => ($tool->assignedUser->last_name) ? e($tool->assignedUser->last_name) : null,
            ] : null;
    }
}
