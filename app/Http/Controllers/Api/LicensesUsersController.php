<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DateFormatter;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\LicensesUsersTransformer;
use App\Models\Company;
use App\Models\LicensesUsers;
use App\Models\SoftwareLicenses;
use DateTime;
use Illuminate\Http\Request;

class LicensesUsersController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $licenseId)
    {
        if (SoftwareLicenses::find($licenseId)) {
            $this->authorize('view', LicensesUsers::class);
            $licenseUsers = Company::scopeCompanyables(LicensesUsers::with('license', 'user')
                ->where('software_licenses_users.software_licenses_id', $licenseId));

            $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

            ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
                ? $limit = $request->input('limit')
                : $limit = config('app.max_results');

            $filter = [];
            if ($request->filled('filter')) {
                $filter = json_decode($request->input('filter'), true);
            }
            if ((!is_null($filter)) && (count($filter)) > 0) {
                $licenseUsers->ByFilter($filter);
            } elseif ($request->filled('search')) {
                $licenseUsers->TextSearch($request->input('search'));
            }

            $allowed_columns = [
                'id',
                'software_licenses_id',
                'assigned_to',
                'created_at',
                'checkout_at'
            ];

            if ($request->filled('dateFrom', 'dateTo')) {
                $filterByDate = DateFormatter::formatDate($request->input('dateFrom'), $request->input('dateTo'));
                $licenseUsers->whereBetween('software_licenses_users.checkout_at', [$filterByDate]);
            }

            $sort = $request->input('sort');

            $default_sort = in_array($sort, $allowed_columns) ? $sort : 'software_licenses_users.created_at';

            switch ($sort) {
                case 'license_active':
                    $licenseUsers->OrderBy('checkout_at', $order);
                    break;
                    
                case 'assigned_user':
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
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/licenses/message.does_not_exist')), 200);
    }
}
