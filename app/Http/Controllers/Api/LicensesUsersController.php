<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\LicensesUsersTransformer;
use App\Models\Company;
use App\Models\LicensesUsers;
use App\Models\SoftwareLicenses;
use Illuminate\Http\Request;

class LicensesUsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $licenseId)
    {
        if (SoftwareLicenses::find($licenseId)) {
            $this->authorize('view', LicensesUsers::class);
            $licenseUsers = Company::scopeCompanyables(LicensesUsers::with('license', 'user')
                ->where('software_licenses_users.software_licenses_id', $licenseId));

            $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
            $licenseUsers->orderBy('id', $order);

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
                'assigned_to',
                'software_licenses_id',
                'created_at',
            ];

            $sort = $request->input('sort');

            $default_sort = in_array($sort, $allowed_columns) ? $sort : 'software_licenses_users.created_at';

            switch ($sort) {
                case 'license':
                    $licenseUsers->OrderLicense($order);
                    break;
                case 'assigned':
                    $licenseUsers->OrderAssigned($order);
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


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
