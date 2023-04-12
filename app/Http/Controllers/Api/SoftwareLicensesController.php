<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\SoftwareLicensesTransformer;
use App\Models\Company;
use App\Models\LicensesUsers;
use App\Models\Software;
use App\Models\SoftwareLicenses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoftwareLicensesController extends Controller
{
    /**
     * Display a listing of the resource by software.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $softwareId)
    {
        $this->authorize('view', SoftwareLicenses::class);
        $licenses = Company::scopeCompanyables(
            SoftwareLicenses::select('software_licenses.*')
                ->with('software')
                ->withCount('freeSeats as free_seats_count')
                ->where('software_id', '=', $softwareId)
        );
        $allowed_columns = [
            'id',
            'software_id',
            'licenses',
            'seats',
            'free_seats_count',
            'purchase_date',
            'expiration_date',
            'purchase_cost'
        ];

        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $licenses->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $licenses->TextSearch($request->input('search'));
        }

        $total = $licenses->count();
        $offset = (($licenses) && ($request->get('offset') > $licenses->count()))
            ? $licenses->count()
            : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        $field_sort = $request->input('sort');
        $default_sort = in_array($field_sort, $allowed_columns) ? $field_sort : 'software_licenses.created_at';

        $licenses->orderBy($default_sort, $order);

        $licenses = $licenses->skip($offset)->take($limit)->get();
        return (new SoftwareLicensesTransformer)->transformSoftwareLicenses($licenses, $total);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', SoftwareLicenses::class);
        $license = new SoftwareLicenses();

        $license->fill($request->all());
        $license->licenses = $request->get('licenses');
        $license->user_id = Auth::id();
        if ($license->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $license, trans('admin/licenses/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $license->getErrors()));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', SoftwareLicenses::class);
        $license = SoftwareLicenses::withCount('freeSeats as free_seats_count')
            ->with('assignedUsers')->findOrFail($id);
        return (new SoftwareLicensesTransformer)->transformSoftwareLicense($license);
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
        $this->authorize('update', SoftwareLicenses::class);
        $license = SoftwareLicenses::find($id);
        if ($license) {
            $license->fill($request->all());
            $license->licenses = $request->get('licenses');
            if ($license->save()) {
                return response()->json(Helper::formatStandardApiResponse('success', $license, trans('admin/licenses/message.update.success')));
            }
            return response()->json(Helper::formatStandardApiResponse('error', null, $license->getErrors()), 200);
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/licenses/message.does_not_exist')), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $license = SoftwareLicenses::findOrFail($id);
        $this->authorize('delete', $license);
        if ($license->delete()) {
            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/licenses/message.delete.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/licenses/message.does_not_exist')), 200);
    }

    public function multiCheckout(Request $request){
        $this->authorize('checkout', SoftwareLicenses::class);
        $softwares = $request->get('softwares');
        foreach( $softwares as $software_id ){
            $software = Software::find($software_id);
            $license = SoftwareLicenses::where('software_id', $software_id)->first();
            $license_user = new LicensesUsers();
            $license_user->software_licenses_id = $license->id;
            $license_user->assigned_to = $request->input('assigned_user');
            $license_user->created_at = Carbon::now();
            $license_user->user_id = Auth::id();
            $license_user->save();
            $license_user->license->seats -= 1;
            $license_user->license->save();
        }

        return response()->json(Helper::formatStandardApiResponse('success', ['license' => e($license_user->license->licenses)], trans('admin/licenses/message.checkout.success')));
    }

    public function checkOut(Request $request, $id)
    {
        $this->authorize('checkout', SoftwareLicenses::class);
        $license_user = new LicensesUsers();
        $license_user->software_licenses_id = $id;
        $license_user->assigned_to = $request->input('assigned_user');
        $license_user->created_at = Carbon::now();
        $license_user->user_id = Auth::id();
        if ($license_user->save()) {
            $license_user->license->seats -= 1;
            if($license_user->license->save()){
                return response()->json(Helper::formatStandardApiResponse('success', ['license' => e($license_user->license->licenses)], trans('admin/licenses/message.checkout.success')));
            }
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $license_user->getErrors()), 200);
    }
}
