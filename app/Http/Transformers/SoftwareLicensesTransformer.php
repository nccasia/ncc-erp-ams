<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Software;
use App\Models\SoftwareLicenses;
use Gate;
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
            'allocated_seats_count' => (int) $license->allocated_seats_count,
            'free_seats_count' => (int) $license->seats - $license->allocated_seats_count,
            'software' =>  ($license->software) ? ['id' => (int) $license->software->id, 'name'=> e($license->software->name)] : null,
            'purchase_date' => Helper::getFormattedDateObject($license->purchase_date, 'datetime'),
            'expiration_date' => Helper::getFormattedDateObject($license->expiration_date, 'datetime'),
            'purchase_cost' => Helper::formatCurrencyOutput($license->purchase_cost),
            'purchase_cost_numeric' => $license->purchase_cost,
            'created_at' => Helper::getFormattedDateObject($license->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($license->updated_at, 'datetime'),
            'deleted_at' => Helper::getFormattedDateObject($license->deleted_at, 'datetime'),
            'user_can_checkout' => (bool) ($license->allocated_seats_count < $license->seats),
        ];

        $permissions_array['available_actions'] = [
            'checkout' => Gate::allows('checkout', License::class),
            'clone' => Gate::allows('create', License::class),
            'update' => Gate::allows('update', License::class),
            'delete' => Gate::allows('delete', License::class),
        ];

        $array += $permissions_array;

        return $array;
    }

    public function transformAssetsDatatable($softwareLicenses)
    {
        return (new DatatablesTransformer)->transformDatatables($softwareLicenses);
    }
}
