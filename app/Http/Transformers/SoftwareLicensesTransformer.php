<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\SoftwareLicenses;
use Illuminate\Database\Eloquent\Collection;

class SoftwareLicensesTransformer
{
    public function transformSoftwareLicenses(Collection $softwaresLicenses, $total)
    {
        $array = [];
        foreach ($softwaresLicenses as $license) {
            $array[] = self::transformSoftwareLicense($license);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformSoftwareLicense(SoftwareLicenses $license)
    {   
        $array = [
            'id' => (int) $license->id,
            'licenses' => e($license->licenses),
            'seats' => (int) $license->seats,
            'checkout_count' => (int) $license->checkout_count,
            'free_seats_count' => (int) $license->seats - $license->checkout_count,
            'software' => ($license->software) ? ['id' => (int) $license->software->id, 'name' => e($license->software->name)] : null,
            'category' => ($license->software->category) ? [
                'id' => (int) $license->software->category->id,
                'name' => e($license->software->category->name)
            ] : null,
            'manufacturer' => ($license->software->manufacturer) ? [
                'id' => (int) $license->software->manufacturer->id,
                'name' => e($license->software->manufacturer->name)
            ] : null,
            'purchase_date' => Helper::getFormattedDateObject($license->purchase_date, 'date'),
            'expiration_date' => Helper::getFormattedDateObject($license->expiration_date, 'date'),
            'purchase_cost' => Helper::formatCurrencyOutput($license->purchase_cost),
            'purchase_cost_numeric' => $license->purchase_cost,
            'created_at' => Helper::getFormattedDateObject($license->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($license->updated_at, 'datetime'),
            'deleted_at' => Helper::getFormattedDateObject($license->deleted_at, 'datetime'),
            'checkout_at' => (count($license->allocatedSeats) > 0) ?
                    Helper::getFormattedDateObject($license->allocatedSeats[0]->checkout_at, 'datetime') :
                    null,
            'notes' => (count($license->allocatedSeats) > 0) ? $license->allocatedSeats[0]->notes : null,
            'user_can_checkout' => (bool) ($license->availableForCheckout()),
        ];
        return $array;
    }

    public function transformAssetsDatatable($softwareLicenses)
    {
        return (new DatatablesTransformer)->transformDatatables($softwareLicenses);
    }
}