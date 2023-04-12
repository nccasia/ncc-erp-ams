<?php

namespace App\Http\Transformers;

use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\LicensesUsers;
use App\Models\Software;
use Gate;
use Illuminate\Database\Eloquent\Collection;

class LicensesUsersTransformer
{
    public function transformLicensesUsers(Collection $licensesUsers, $total)
    {
        $array = [];
        $seat_count = 0;
        foreach ($licensesUsers as $licenseUsers) {
            $seat_count++;
            $array[] = self::transformLicensesUser($licenseUsers, $seat_count);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformLicensesUser(LicensesUsers $licenseUsers, $seat_count = 0)
    {
        $array = [
            'id' => (int) $licenseUsers->id,
            'license_active' => [
                'id' => (int) $licenseUsers->license->id,
                'name'=>  $licenseUsers->license->licenses,
            ],
            'assigned_user' => ($licenseUsers->user) ? [
                'id' => (int) $licenseUsers->user->id,
                'name'=> e($licenseUsers->user->present()->fullName),
            ] : null,
            'user_can_checkout' => $licenseUsers->assigned_to == '',
        ];
        $permissions_array['available_actions'] = [
            'checkout' => Gate::allows('checkout', Software::class),
            'checkin' => Gate::allows('checkin', Software::class),
            'clone' => Gate::allows('create', Software::class),
            'update' => Gate::allows('update', Software::class),
            'delete' => Gate::allows('delete', Software::class),
        ];

        $array += $permissions_array;

        return $array;
    }
}
