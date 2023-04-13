<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Software;
use Gate;
use Illuminate\Database\Eloquent\Collection;

class SoftwaresTransformer
{
    public function transformSoftwares(Collection $softwares, $total)
    {
        $array = [];
        foreach ($softwares as $software) {
            $array[] = self::transformSoftware($software);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformSoftware(Software $software)
    {
        $array = [
            'id' => (int) $software->id,
            'name' => e($software->name),
            'software_tag' => e($software->software_tag),
            'version' => e($software->version),
            'user' =>  ($software->user) ? ['id' => (int) $software->user->id, 'name'=> e($software->user->username)] : null,
            'manufacturer' =>  ($software->manufacturer) ? [
                'id' => (int) $software->manufacturer->id, 
                'name'=> e($software->manufacturer->name),
                'url'=> e($software->manufacturer->url),
                'support_url'=> e($software->manufacturer->support_url),
                'support_phone'=> e($software->manufacturer->support_phone),
                'support_email'=> e($software->manufacturer->support_email),
                ] : null,
            'category' =>  ($software->category) ? [
                'id' => (int) $software->category->id, 
                'name'=> e($software->category->name),
                'category_type'=> e($software->category->category_type)
                ] : null,
            'total_licenses' => (int) $software->total_licenses,
            'softwareLicenses' => $software->softwareLicenses,
            'notes' => e($software->notes),
            'user_can_checkout'=> $software->total_licenses > 0 ? true : false,
            'created_at' => Helper::getFormattedDateObject($software->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($software->updated_at, 'datetime'),
            'deleted_at' => Helper::getFormattedDateObject($software->deleted_at, 'datetime'),
        ];

        $permissions_array['available_actions'] = [
            'checkout' => Gate::allows('checkout', Software::class),
            'clone' => Gate::allows('create', Software::class),
            'update' => Gate::allows('update', Software::class),
            'delete' => Gate::allows('delete', Software::class),
        ];

        $array += $permissions_array;

        return $array;
    }

    public function transformAssetsDatatable($softwares)
    {
        return (new DatatablesTransformer)->transformDatatables($softwares);
    }
}
