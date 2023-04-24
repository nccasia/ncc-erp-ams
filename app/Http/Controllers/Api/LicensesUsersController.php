<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\LicensesUsersTransformer;
use App\Models\Company;
use App\Models\LicensesUsers;
use App\Models\Software;
use App\Models\SoftwareLicenses;
use Illuminate\Http\Request;

class LicensesUsersController extends Controller
{
    /**
     *  Get the list users have checked out of license
     *
     * @param Request $request
     * @param  int  $licenseId
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function showUsersLicense(Request $request, $licenseId)
    {
        if (SoftwareLicenses::find($licenseId)) {
            $this->authorize('view', LicensesUsers::class);
            $licenseUsers = Company::scopeCompanyables(LicensesUsers::with('license', 'user')
                ->where('software_licenses_users.software_licenses_id', $licenseId));

            $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

            ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
                ? $limit = $request->input('limit')
                : $limit = config('app.max_results');

            $allowed_columns = [
                'id',
                'software_licenses_id',
                'assigned_to',
                'created_at',
                'checkout_at'
            ];

            $sort = $request->input('sort');

            $default_sort = in_array($sort, $allowed_columns) ? $sort : 'software_licenses_users.created_at';

            switch ($sort) {
                case 'checkout_at':
                    $licenseUsers->OrderBy('checkout_at', $order);
                    break;

                case 'user_id':
                    $licenseUsers->OrderByUserId($order);
                    break;

                case 'name':
                    $licenseUsers->OrderAssigned($order);
                    break;

                case 'department':
                    $licenseUsers->OrderDepartment($order);
                    break;

                case 'location':
                    $licenseUsers->OrderLocation($order);
                    break;

                default:
                    $licenseUsers->OrderBy($default_sort, $order);
            }

            $total = $licenseUsers->count();
            $offset = (($licenseUsers) && (request('offset') > $total)) ? 0 : request('offset', 0);
            $licenseUsers = $licenseUsers->skip($offset)->take($limit)->get();

            if ($licenseUsers) {
                return (new LicensesUsersTransformer)->transformLicensesUsers($licenseUsers, $total);
            }
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/licenses/message.does_not_exist')));
    }

    /**
     * Get the list users have checked out of software
     *
     * @param Request $request
     * @param  int  $licenseId
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function showUsersSoftware(Request $request, $softwareId)
    {
        if (Software::find($softwareId)) {
            $this->authorize('view', LicensesUsers::class);
            $licenseUsers = Company::scopeCompanyables(LicensesUsers::join('software_licenses', 'software_licenses.id', 'software_licenses_users.software_licenses_id')
                ->where('software_licenses.software_id', $softwareId)
                ->groupBy('assigned_to')
                ->with('license', 'user'));

            $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

            ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
                ? $limit = $request->input('limit')
                : $limit = config('app.max_results');

            $allowed_columns = [
                'id',
                'software_licenses_id',
                'assigned_to',
                'created_at',
                'checkout_at'
            ];

            $sort = $request->input('sort');

            $default_sort = in_array($sort, $allowed_columns) ? $sort : 'software_licenses_users.created_at';

            switch ($sort) {
                case 'checkout_at':
                    $licenseUsers->OrderBy('checkout_at', $order);
                    break;

                case 'id':
                    $licenseUsers->OrderByUserId($order);
                    break;

                case 'name':
                    $licenseUsers->OrderAssigned($order);
                    break;

                case 'department':
                    $licenseUsers->OrderDepartment($order);
                    break;

                case 'location':
                    $licenseUsers->OrderLocation($order);
                    break;

                default:
                    $licenseUsers->OrderBy($default_sort, $order);
            }

            $total = $licenseUsers->count();
            $offset = (($licenseUsers) && (request('offset') > $total)) ? 0 : request('offset', 0);
            $licenseUsers = $licenseUsers->skip($offset)->take($limit)->get();

            if ($licenseUsers) {
                return (new LicensesUsersTransformer)->transformLicensesUsers($licenseUsers, $total);
            }
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/licenses/message.does_not_exist')));
    }
}