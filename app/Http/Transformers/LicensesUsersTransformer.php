<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\LicensesUsers;
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
                'name'=>  e($licenseUsers->license->licenses),
            ],
            'assigned_user' => ($licenseUsers->user) ? [
                'user_id' => (int) $licenseUsers->user->id,
                'name'=> e($licenseUsers->user->fullName),
                'department' => $licenseUsers->user->department ? e($licenseUsers->user->department->name) : null,
                'location' => $licenseUsers->user->department ? e($licenseUsers->user->department->location->name) : null
            ] : null,
            'checkout_at'=>  Helper::getFormattedDateObject($licenseUsers->checkout_at, 'datetime'),
        ];

        return $array;
    }
}
