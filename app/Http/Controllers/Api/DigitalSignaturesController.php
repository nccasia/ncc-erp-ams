<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Helpers\DateFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request as RequestsRequest;
use App\Http\Transformers\DigitalSignaturesTransformer;
use App\Jobs\SendConfirmCheckinMail;
use App\Jobs\SendConfirmCheckoutMail;
use App\Jobs\SendRejectCheckinMail;
use App\Jobs\SendRejectCheckoutMail;
use App\Jobs\SendCheckoutMailDigitalSignature;
use App\Models\Category;
use App\Models\Location;
use App\Jobs\SendCheckinMailDigitalSignature;
use App\Models\AssetHistory;
use App\Models\DigitalSignatures;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DigitalSignaturesController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return array
     */
    public function index(request $request)
    {
        $this->authorize('view', DigitalSignatures::class);

        $digital_signatures = DigitalSignatures::select('digital_signatures.*')
            ->with('user', 'supplier', 'assignedUser', 'location', 'category', 'tokenStatus');
        $digital_signatures = $this->filters($digital_signatures, $request);

        $offset = ($digital_signatures && ($request->get('offset') > $digital_signatures->count()))
            ? $digital_signatures->count()
            : $request->get('offset', 0);

        $limit = ((config('app.max_results') >= $request->input('limit')) && $request->filled('limit'))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $allowed_columns = [
            'id',
            'seri',
            'created_at',
            'purchase_date',
            'expiration_date',
            'purchase_cost',
            'status',
            'checkout_date',
            'checkin_date',
            'note',
            'qty'
        ];
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = $request->input('sort');

        $default_sort = in_array($sort, $allowed_columns)
            ? $sort
            : 'digital_signatures.created_at';

        switch ($sort) {
            case 'user':
                $digital_signatures->orderUser($order);
                break;
            case 'assigned_to':
                $digital_signatures->orderAssignToUser($order);
                break;
            case 'supplier':
                $digital_signatures->OrderSupplier($order);
                break;
            case 'location':
                $digital_signatures->OrderLocation($order);
                break;
            case 'category':
                $digital_signatures->OrderCategory($order);
                break;
            default:
                $digital_signatures->orderBy($default_sort, $order);
        }

        $total = $digital_signatures->count();
        $digital_signatures = $digital_signatures->skip($offset)->take($limit)->get();

        return (new DigitalSignaturesTransformer())->transformSignatures($digital_signatures, $total);
    }

    public function getTotalDetail(request $request)
    {
        $this->authorize('view', DigitalSignatures::class);
        $digital_signatures = DigitalSignatures::select('digital_signatures.*');
        $digital_signatures = $this->filters($digital_signatures, $request);

        $total_dg_by_category = $digital_signatures->selectRaw('c.name as name , count(*) as total')
            ->join('categories as c', 'c.id', '=', 'digital_signatures.category_id')
            ->groupBy('c.name')
            ->pluck('total', 'c.name');
        $total_detail = $total_dg_by_category->map(function ($value, $key) {
            return [
                'name' => $key,
                'total' => $value
            ];
        })->values()->toArray();

        return response()->json(Helper::formatStandardApiResponse('success', $total_detail, null));
    }

    public function filters($digital_signatures, $request)
    {
        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        if ($request->supplier) {
            $digital_signatures->InSupplier($request->input('supplier'));
        }

        if ($request->status_label) {
            $digital_signatures->InStatus($request->input('status_label'));
        }

        if ($request->filled('assigned_status')) {
            $digital_signatures->InAssignedStatus($request->input('assigned_status'));
        }

        if ($request->filled('WAITING_CHECKOUT') || $request->filled('WAITING_CHECKIN')) {
            $digital_signatures->where(function ($query) use ($request) {
                $query->where('digital_signatures.assigned_status', '=', $request->input('WAITING_CHECKOUT'))
                    ->orWhere('digital_signatures.assigned_status', '=', $request->input('WAITING_CHECKIN'));
            });
        }

        if ($request->filled('purchaseDateFrom', 'purchaseDateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('purchaseDateFrom'), $request->input('purchaseDateTo'));
            $digital_signatures->whereBetween('digital_signatures.purchase_date', [$filterByDate]);
        }
        if ($request->filled('expirationDateFrom', 'expirationDateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('expirationDateFrom'), $request->input('expirationDateTo'));
            $digital_signatures->whereBetween('digital_signatures.expiration_date', [$filterByDate]);
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $digital_signatures->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $digital_signatures->TextSearch($request->input('search'));
        }

        return $digital_signatures;
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', DigitalSignatures::class);
        $digitalSignatures = new DigitalSignatures();
        $digitalSignatures->name = $request->get('name');
        $digitalSignatures->seri = $request->get('seri');
        $digitalSignatures->supplier_id = $request->get('supplier_id');
        $digitalSignatures->user_id = Auth::id();
        $digitalSignatures->purchase_date = $request->get('purchase_date');
        $digitalSignatures->purchase_cost = $request->get('purchase_cost');
        $digitalSignatures->expiration_date = $request->get('expiration_date');
        $digitalSignatures->assigned_status = config('enum.status_tax_token.NOT_ACTIVE');
        $digitalSignatures->status_id = $request->get('status_id');
        $digitalSignatures->location_id = $request->get('location_id');
        $digitalSignatures->category_id = $request->get('category_id');
        $digitalSignatures->warranty_months = $request->get('warranty_months');
        $digitalSignatures->qty = $request->get('qty');

        if (!$digitalSignatures->save()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $digitalSignatures->getErrors()), Response::HTTP_BAD_REQUEST);
        }

        if ($request->get('assigned_to')) {
            // $digitalSignatures->checkOut(
            //     $assigned_to = $request->get('assigned_to'),
            //     $assigned_status = config('enum.assigned_status.WAITINGCHECKOUT'),
            //     $checkout_date = Carbon::now()->format('d-m-Y H:i:s'),
            //     $assigned_type = 'user'
            // );
            if (!$digitalSignatures->save()) {
                return response()->json(Helper::formatStandardApiResponse('error', null, $digitalSignatures->getErrors()));
            }
        }

        return response()->json(Helper::formatStandardApiResponse('success', $digitalSignatures, trans('admin/digital_signatures/message.create.success')));
    }

    /**
     * Display the specified resource.
     * 
     * @param int $id
     * 
     * @return array
     */
    public function show(int $id)
    {
        $this->authorize('view', DigitalSignatures::class);
        $digitalSignature = DigitalSignatures::with('user', 'supplier', 'assignedUser')
            ->findOrFail($id);

        return (new DigitalSignaturesTransformer())->transformSignature($digitalSignature);
    }

    /**
     * Update the specified resource in storage.
     * 
     * @param \Illuminate\Http\Request Request $request
     * @param int $id
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $this->authorize('update', DigitalSignatures::class);
        $signature = DigitalSignatures::findOrFail($id);
        $assigned_status = $signature->assigned_status;
        $signature->fill($request->all());
        $user = null;
        if ($signature->assigned_to) {
            $user = User::find($signature->assigned_to);
        }
        if ($user && $request->has('assigned_status') && $assigned_status !== $request->get('assigned_status')) {
            $signature->assigned_status = $request->get('assigned_status');
            $it_ncc_email = Setting::first()->admin_cc_email;
            $user_name = $user->first_name . ' ' . $user->last_name;
            $current_time = Carbon::now();
            $data = [
                'user_name' => $user_name,
                'is_confirm' => '',
                'seri' => $signature->seri,
                'time' => $current_time->format('d-m-Y'),
                'reason' => '',
            ];
            if ($signature->assigned_status == config('enum.assigned_status.ACCEPT')) {
                $data['signatures_count'] = 1;
                if ($signature->withdraw_from) {
                    $signature->increment('checkin_counter', 1);
                    $data['is_confirm'] = 'đã xác nhận thu hồi';
                    $signature->status_id = config('enum.status_id.READY_TO_DEPLOY');
                    $signature->assigned_status = config('enum.assigned_status.DEFAULT');
                    $signature->withdraw_from = null;
                    $signature->last_checkout = null;
                    $signature->assigned_to = null;
                    SendConfirmCheckinMail::dispatch($data, $it_ncc_email);
                } else {
                    $signature->increment('checkout_counter', 1);
                    $data['is_confirm'] = 'đã xác nhận cấp phát';
                    $signature->status_id = config('enum.status_id.ASSIGN');
                    SendConfirmCheckoutMail::dispatch($data, $it_ncc_email);
                }
            } elseif ($signature->assigned_status == config('enum.assigned_status.REJECT')) {
                $data['signatures_count'] = 1;
                if ($signature->withdraw_from) {
                    $data['is_confirm'] = 'đã từ chối thu hồi';
                    $signature->status_id = config('enum.status_id.ASSIGN');
                    $signature->assigned_status = config('enum.assigned_status.ACCEPT');
                    $data['reason'] = 'Lý do: ' . $request->get('reason');
                    SendRejectCheckinMail::dispatch($data, $it_ncc_email);
                } else {
                    $data['is_confirm'] = 'đã từ chối nhận';
                    $signature->status_id = config('enum.status_id.READY_TO_DEPLOY');
                    $signature->assigned_status = config('enum.assigned_status.DEFAULT');
                    $data['reason'] = 'Lý do: ' . $request->get('reason');
                    $signature->withdraw_from = null;
                    $signature->last_checkout = null;
                    $signature->assigned_to = null;;
                    SendRejectCheckoutMail::dispatch($data, $it_ncc_email);
                }
            }
        }
        if (!$signature->save()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $signature->getErrors()), Response::HTTP_BAD_REQUEST);
        }

        return response()->json(Helper::formatStandardApiResponse('success', $signature, trans('admin/digital_signatures/message.update.success', ['signature' => $signature->seri])));
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $this->authorize('delete', DigitalSignatures::class);
        $signature = DigitalSignatures::findOrFail($id);
        if (!$signature->delete()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $signature->getErrors()));
        }

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/digital_signatures/message.delete.success', ['seri' => $signature->seri])));
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param int $digital_signature_id
     * @param \Illuminate\Http\Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Request $request, int $digital_signature_id)
    {
        $this->authorize('checkout', DigitalSignatures::class);
        $signature = DigitalSignatures::findOrFail($digital_signature_id);
        if (!$signature->availableForCheckout()) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    ['signature' => e($signature->seri)],
                    trans('admin/digital_signatures/message.checkout.not_available')
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
        $target = User::find($request->get('assigned_to'));

        $checkout_date = $request->get('checkout_date');
        $request->get('note') ? $note = $request->get('note') : $note = null;
        $signature->status_id = config('enum.status_id.ASSIGN');
        if ($signature->checkOut($target, $checkout_date, $note, $signature->name, config('enum.assigned_status.WAITINGCHECKOUT'))) {
            $this->saveSignatureHistory($digital_signature_id, config('enum.asset_history.CHECK_IN_TYPE'));
            $this->sendCheckoutMail($target, $signature);
            return response()->json(Helper::formatStandardApiResponse('success', ['digital_signature' => e($signature->seri)], trans('admin/digital_signatures/message.checkout.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/digital_signatures/message.checkout.error')), Response::HTTP_BAD_REQUEST);
    }

    public function multiCheckout(Request $request)
    {
        $this->authorize('checkout', Asset::class);

        $signatures = request('signatures');
        $assigned_to = $request->get('assigned_to');
        $checkout_date = $request->get('checkout_date');
        $request->get('note') ? $note = $request->get('note') : $note = null;
        $target = User::find($request->get('assigned_to'));
        foreach ($signatures as $signature_id) {
            $signature = DigitalSignatures::findOrFail($signature_id);
            if (!$signature->availableForCheckout()) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        ['digital_signature' => e($signature->seri)],
                        trans('admin/digital_signatures/message.checkout.not_available')
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $signature->status_id = config('enum.status_id.ASSIGN');
            if ($signature->checkOut($target, $checkout_date, $note, $signature->name, config('enum.assigned_status.WAITINGCHECKOUT'))) {
                $this->saveSignatureHistory($signature_id, config('enum.asset_history.CHECK_IN_TYPE'));
                $signature_name = $signature->name;
            } else {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        trans('admin/digital_signatures/message.checkout.error')
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        $this->sendCheckoutMail($target, $signature);
        return response()->json(Helper::formatStandardApiResponse('success', ['digital_signature' => e($signature->seri)], trans('admin/digital_signatures/message.checkout.success')));
    }

    public function checkIn(Request $request, $signature_id)
    {
        $this->authorize('checkin', DigitalSignatures::class);
        $signature = DigitalSignatures::findOrFail($signature_id);
        if (is_null($target = $signature->assigned_to)) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    ['signature' => e($signature->seri)],
                    trans('admin/digital_signatures/message.checkin.already_checked_in')
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
        if (!$signature->availableForCheckin()) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    ['asset' => e($signature->seri)],
                    trans('admin/digital_signatures/message.checkin.not_available')
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $checkin_date = $request->get('checkin_at');
        $request->get('note') ? $note = $request->get('note') : $note = null;
        $target = User::findOrFail($signature->assigned_to);

        if ($signature->checkIn($target, $checkin_date, $note, $signature->name, config('enum.assigned_status.WAITINGCHECKIN'))) {
            $this->saveSignatureHistory($signature_id, config('enum.asset_history.CHECK_IN_TYPE'));

            $user = $signature->assignedTo;
            $this->sendCheckinMail($user, $signature);
            return response()->json(Helper::formatStandardApiResponse('success', ['digital_signature' => e($signature->seri)], trans('admin/digital_signatures/message.checkin.success')));
        }
        return response()->json(
            Helper::formatStandardApiResponse(
                'error',
                null,
                trans('admin/digital_signatures/message.checkin.error')
            ),
            Response::HTTP_BAD_REQUEST
        );
    }

    public function multiCheckin(Request $request)
    {
        $this->authorize('checkin', DigitalSignatures::class);

        $signatures = request('signatures');
        $checkin_date = $request->get('checkout_at');
        $request->get('note') ? $note = $request->get('note') : $note = null;

        foreach ($signatures as $signature_id) {
            $signature = DigitalSignatures::findOrFail($signature_id);
            if (is_null($target = $signature->assigned_to)) {
                return response()->json(Helper::formatStandardApiResponse('error', ['signature' => e($signature->seri)], trans('admin/digital_signatures/message.checkin.already_checked_in')));
            }
            if (!$signature->availableForCheckin()) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        ['asset' => e($signature->seri)],
                        trans('admin/digital_signatures/message.checkin.not_available')
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $checkin_date = $request->get('checkin_at');
            $request->get('note') ? $note = $request->get('note') : $note = null;
            $target = User::findOrFail($signature->assigned_to);

            if ($signature->checkIn($target, $checkin_date, $note, $signature->name, config('enum.assigned_status.WAITINGCHECKIN'))) {
                $this->saveSignatureHistory($signature_id, config('enum.asset_history.CHECK_IN_TYPE'));
                // $data = [
                //     'user_name' => $user_name,
                //     'asset_name' => $asset->name,
                //     'count' => 1,
                //     'location_address' => $location_address,
                //     'time' => $current_time->format('d-m-Y'),
                //     'link' => config('client.my_assets.link'),
                // ];

                // SendCheckoutMail::dispatch($data, $user_email);

            } else {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        trans('admin/digital_signatures/message.checkin.error')
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }
        }
        return response()->json(Helper::formatStandardApiResponse('success', ['digital_signature' => e($signature->seri)], trans('admin/digital_signatures/message.checkin.success')));
    }

    public function saveSignatureHistory($signature_id, $type)
    {
        $signature = DigitalSignatures::find($signature_id);
        AssetHistory::create([
            'creator_id' => Auth::user()->id,
            'type' => $type,
            'assigned_to' => $signature->assigned_to,
            'user_id' => $signature->user_id
        ]);
    }

    public function multiUpdate(Request $request)
    {
        $this->authorize('update', DigitalSignatures::class);
        $signatures_id = $request->tax_tokens;
        foreach ($signatures_id as $id) {
            $signature = DigitalSignatures::findOrFail($id);
            $assigned_status = $signature->assigned_status;
            $signature->fill($request->all());
            $user = null;
            if ($signature->assigned_to) {
                $user = User::find($signature->assigned_to);
            }
            if ($user && $request->has('assigned_status') && $assigned_status !== $request->get('assigned_status')) {
                $signature->assigned_status = $request->get('assigned_status');
                $it_ncc_email = Setting::first()->admin_cc_email;
                $user_name = $user->first_name . ' ' . $user->last_name;
                $current_time = Carbon::now();
                $data = [
                    'user_name' => $user_name,
                    'is_confirm' => '',
                    'seri' => $signature->seri,
                    'time' => $current_time->format('d-m-Y'),
                    'reason' => '',
                ];
                if ($signature->assigned_status == config('enum.assigned_status.ACCEPT')) {
                    $data['signatures_count'] = 1;
                    if ($signature->withdraw_from) {
                        $signature->increment('checkin_counter', 1);
                        $data['is_confirm'] = 'đã xác nhận thu hồi';
                        $signature->status_id = config('enum.status_id.READY_TO_DEPLOY');
                        $signature->assigned_status = config('enum.assigned_status.DEFAULT');
                        $signature->withdraw_from = null;
                        $signature->last_checkout = null;
                        $signature->assigned_to = null;
                        SendConfirmCheckinMail::dispatch($data, $it_ncc_email);
                    } else {
                        $signature->increment('checkout_counter', 1);
                        $data['is_confirm'] = 'đã xác nhận cấp phát';
                        $signature->status_id = config('enum.status_id.ASSIGN');
                        SendConfirmCheckoutMail::dispatch($data, $it_ncc_email);
                    }
                } elseif ($signature->assigned_status == config('enum.assigned_status.REJECT')) {
                    $data['signatures_count'] = 1;
                    if ($signature->withdraw_from) {
                        $data['is_confirm'] = 'đã từ chối thu hồi';
                        $signature->status_id = config('enum.status_id.ASSIGN');
                        $signature->assigned_status = config('enum.assigned_status.ACCEPT');
                        $data['reason'] = 'Lý do: ' . $request->get('reason');
                        SendRejectCheckinMail::dispatch($data, $it_ncc_email);
                    } else {
                        $data['is_confirm'] = 'đã từ chối nhận';
                        $signature->status_id = config('enum.status_id.READY_TO_DEPLOY');
                        $signature->assigned_status = config('enum.assigned_status.DEFAULT');
                        $data['reason'] = 'Lý do: ' . $request->get('reason');
                        $signature->withdraw_from = null;
                        $signature->last_checkout = null;
                        $signature->assigned_to = null;;
                        SendRejectCheckoutMail::dispatch($data, $it_ncc_email);
                    }
                }
            }
            if (!$signature->save()) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        $signature->getErrors()
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        return response()->json(Helper::formatStandardApiResponse('success', $signature, trans('admin/digital_signatures/message.update.success', ['signature' => "lol"])));
    }

    public function sendCheckoutMail($user, $signature)
    {
        $data = [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'signature_name' => $signature->name,
            'count' => 1,
            'location_address' => null,
            'time' => Carbon::now()->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];

        SendCheckoutMailDigitalSignature::dispatch($data, $user->email);
    }

    public function sendCheckinMail($user, $signature)
    {
        $data = [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'signature_name' => $signature->name,
            'count' => 1,
            'location_address' => null,
            'time' => Carbon::now()->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];

        SendCheckinMailDigitalSignature::dispatch($data, $user->email);
    }
}
