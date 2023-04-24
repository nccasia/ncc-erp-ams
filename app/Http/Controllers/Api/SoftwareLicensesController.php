<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DateFormatter;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\SoftwareLicensesTransformer;
use App\Jobs\SendCheckoutMail;
use App\Jobs\SendCheckoutMailSoftware;
use App\Models\Company;
use App\Models\LicensesUsers;
use App\Models\Software;
use App\Models\SoftwareLicenses;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SoftwareLicensesController extends Controller
{

    /**
     * Display a listing of the resource by software.
     *
     * @param Request $request
     * @param int $softwareId
     * 
     * @return array
     */
    public function index(Request $request, $softwareId)
    {
        $this->authorize('view', SoftwareLicenses::class);

        $licenses = Company::scopeCompanyables(
            SoftwareLicenses::select('software_licenses.*')
                ->with('software')
                ->withCount('allocatedSeats as allocated_seats_count')
                ->where('software_id', '=', $softwareId)
        );

        $allowed_columns = [
            'id',
            'software_id',
            'checkout_count',
            'licenses',
            'seats',
            'purchase_date',
            'expiration_date',
            'purchase_cost',
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

        if ($request->filled('dateFrom', 'dateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('dateFrom'), $request->input('dateTo'));
            $licenses->whereBetween('software_licenses.purchase_date', [$filterByDate]);
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', SoftwareLicenses::class);
        $license = new SoftwareLicenses();

        //Return error if expiration date less than purchase date
        $expirationDate = Carbon::parse($request->input('expiration_date'));
        $purchaseDate = Carbon::parse($request->input('purchase_date'));
        if (($purchaseDate->diffInDays($expirationDate, false) < 0)) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    null,
                    ['expiration_date' => trans('admin/licenses/message.create.expiration_date')]
                )
            );
        }
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
     * @return array
     */
    public function show($id)
    {
        $this->authorize('view', SoftwareLicenses::class);
        $license = SoftwareLicenses::withCount('allocatedSeats as allocated_seats_count')
            ->with('assignedUsers')->findOrFail($id);
        return (new SoftwareLicensesTransformer)->transformSoftwareLicense($license);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $this->authorize('update', SoftwareLicenses::class);
        $license = SoftwareLicenses::find($id);
        if ($license) {
            //Return error if expiration date less than purchase date
            $expirationDate = Carbon::parse($request->input('expiration_date'));
            $purchaseDate = Carbon::parse($request->input('purchase_date'));
            if (($purchaseDate->diffInDays($expirationDate, false) < 0)) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        ['expiration_date' => trans('admin/licenses/message.create.expiration_date')]
                    )
                );
            }
            $license->fill($request->all());
            if ($request->get('licenses')) {
                $license->licenses = $request->get('licenses');
            }
            if ($license->save()) {
                return response()->json(Helper::formatStandardApiResponse('success', $license, trans('admin/licenses/message.update.success')));
            }
            return response()->json(Helper::formatStandardApiResponse('error', null, $license->getErrors()));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/licenses/message.does_not_exist')));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $license = SoftwareLicenses::findOrFail($id);
        $this->authorize('delete', $license);
        if ($license->delete()) {
            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/licenses/message.delete.success')));
        }
        return response()->json(Helper::formatStandardApiResponse(
            'error',
            null,
            trans('admin/licenses/message.does_not_exist')
        ), Response::HTTP_NOT_FOUND);
    }

    /**
     * Checkout multi licenses to users
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function multiCheckout(Request $request)
    {
        $this->authorize('checkout', SoftwareLicenses::class);
        $softwares = $request->get('softwares');
        $licenses_active = array();
        $assigned_users = $request->get('assigned_users');

        //Checkout multi softwares to multi users
        foreach ($softwares as $software_id) {
            $software = Software::where('id', $software_id)
                ->withSum([
                    'licenses' => function ($query) {
                        $query->whereNull('deleted_at');
                    }
                ], 'seats')
                ->withSum([
                    'licenses' => function ($query) {
                        $query->whereNull('deleted_at');
                    }
                ], 'checkout_count')
                ->first();

            //Return error if assigned user > licenses's seats of Software or software not available for checkout
            $freeSeatsCount = $software->licenses_sum_seats - $software->licenses_sum_checkout_count;
            if ($freeSeatsCount < count($assigned_users) || !$software || !$software->availableForCheckout()) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        ['assigned_users' => $software->name . ' ' . trans('admin/licenses/message.checkout.not_available')]
                    )
                );
            }

            //Checkout license to assigned users
            $software = Software::find($software_id);
            foreach ($assigned_users as $assigned_user) {
                if (User::find($assigned_user)) {
                    $license = new SoftwareLicenses;
                    $license = $license->getFirstLicenseAvailableForCheckout($software_id, $assigned_user);
                    if ($license) {
                        $license_user = $this->setDataLicenseUser($license, $assigned_user, $request);
                        if ($license_user->save()) {
                            $licenseUpdate = SoftwareLicenses::findOrFail($license->id);
                            $licenseUpdate->update(['checkout_count' => $licenseUpdate->checkout_count + 1]);
                            array_push($licenses_active, $license_user->license->licenses);
                            $this->sendMailCheckOut($assigned_user, $licenseUpdate);
                        }
                    } else {
                        return response()->json(
                            Helper::formatStandardApiResponse(
                                'error',
                                null,
                                ['assigned_users' => $software->name . ' ' . trans('admin/licenses/message.checkout.not_available')]
                            )
                        );
                    }
                }
            }
        }
        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                ['license' => $licenses_active],
                trans('admin/licenses/message.checkout.success')
            )
        );
    }

    /**
     * Checkout one license to users
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkOut(Request $request, $license_id)
    {
        $this->authorize('checkout', SoftwareLicenses::class);
        $license = SoftwareLicenses::findOrFail($license_id);
        $assigned_users = $request->get('assigned_users');
        $this->authorize('checkout', $license);

        // Return error if assigned user > seats of license or license not available for checkout
        if (count($assigned_users) > ($license->seats - $license->checkout_count) || !$license->availableForCheckout()) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    null,
                    ['assigned_users' => trans('admin/licenses/message.checkout.not_available')]
                )
            );
        }

        foreach ($assigned_users as $assigned_user) {

            // Return error if licenses already checkout to User
            $license_user = $license->allocatedSeats()->where('assigned_to', $assigned_user)->first();
            if ($license_user) {
                $user = User::find($license_user->assigned_to);
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        ['assigned_users' => trans('admin/licenses/message.checkout.user_not_available') . $user->username]
                    )
                );
            }

            if (User::find($assigned_user)) {
                $license_user = $this->setDataLicenseUser($license, $assigned_user, $request);
                if ($license_user->save()) {
                    $license->update(['checkout_count' => $license->checkout_count + 1]);
                    $this->sendMailCheckOut($assigned_user, $license);
                }
            }
        }

        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                ['license' => e($license_user->license->licenses)],
                trans('admin/licenses/message.checkout.success')
            )
        );
    }

    /**
     * Set data linceses of user
     *
     * @param  SoftwareLicenses $license
     * @param  int $assigned_user
     * @param  Request $request
     * @return LicensesUsers
     */
    public function setDataLicenseUser($license, $assigned_user, $request)
    {
        $license_user = new LicensesUsers();
        $license_user->software_licenses_id = $license->id;
        $license_user->assigned_to = $assigned_user;
        $license_user->checkout_at = $request->input('checkout_at');
        $license_user->notes = $request->input('notes');
        $license_user->created_at = Carbon::now();
        $license_user->user_id = Auth::id();
        return $license_user;
    }

    /**
     * Send mail to user when checkout
     *
     * @param  int $assigned_user
     * @param  SoftwareLicenses $license
     * @return void
     */
    public function sendMailCheckOut($assigned_user, $license)
    {
        $user = User::find($assigned_user);
        $user_email = $user->email;
        $user_name = $user->first_name . ' ' . $user->last_name;
        $current_time = Carbon::now();
        $data = [
            'user_name' => $user_name,
            'software_name' => $license->software->name,
            'license' => $license->licenses,
            'count' => 1,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendCheckoutMailSoftware::dispatch($data, $user_email);
    }

    /**
     * Return list licenses of user
     *
     * @param  Request $request
     * @return array
     */
    public function assign(Request $request, $audit = null)
    {
        $user_id = Auth::id();

        $allowed_columns = [
            'id',
            'software',
            'licenses',
            'category',
            'manufacturer',
            'purchase_date',
            'expiration_date',
            'checkout_at',
            'purchase_cost'
        ];

        $licenses = Company::scopeCompanyables(
            SoftwareLicenses::select('software_licenses.*')
                ->join('software_licenses_users', 'software_licenses_users.software_licenses_id', 'software_licenses.id')
                ->where('assigned_to', $user_id)
                ->with([
                    'software' => function ($query) {
                        $query->whereNull('deleted_at');
                    }
                ])
                ->with([
                    'allocatedSeats' => function ($query) use ($user_id) {
                        $query->where('assigned_to', $user_id);
                    }
                ])
        );

        $offset = (($licenses) && ($request->get('offset') > $licenses->count())) ? $licenses->count() : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        if ($request->filled('search')) {
            $licenses->TextSearch($request->input('search'));
        }

        $sort_override = str_replace('custom_fields.', '', $request->input('sort'));

        $column_sort = in_array($sort_override, $allowed_columns) ? $sort_override : 'checkout_at';

        switch ($sort_override) {
            case 'software':
                $licenses->OrderSoftware($order);
                break;
            case 'manufacturer':
                $licenses->OrderManufacturer($order);
                break;
            case 'category':
                $licenses->OrderCategories($order);
                break;
            default:
                $licenses->orderBy($column_sort, $order);
                break;
        }

        $total = $licenses->count();
        $licenses = $licenses->skip($offset)->take($limit)->get();
        return (new SoftwareLicensesTransformer)->transformSoftwareLicenses($licenses, $total);
    }
}