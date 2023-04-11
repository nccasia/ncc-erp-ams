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
            'user' =>  ($software->user) ? ['id' => (int) $software->user->id, 'name'=> e($software->user->username)] : null,
            'manufacturer' =>  ($software->manufacturer) ? ['id' => (int) $software->manufacturer->id, 'name'=> e($software->manufacturer->name)] : null,
            'category' =>  ($software->category) ? ['id' => (int) $software->category->id, 'name'=> e($software->category->name)] : null,
            'total_licenses' => (int) $software->total_licenses,
            'notes' => e($software->notes),
            'created_at' => Helper::getFormattedDateObject($software->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($software->updated_at, 'datetime'),
            'deleted_at' => Helper::getFormattedDateObject($software->deleted_at, 'datetime'),
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

    public function transformAssetsDatatable($softwares)
    {
        return (new DatatablesTransformer)->transformDatatables($softwares);
    }
}
