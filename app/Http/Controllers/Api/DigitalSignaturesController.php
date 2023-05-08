<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request as RequestsRequest;
use App\Http\Transformers\DigitalSignaturesTransformer;
use App\Models\DigitalSignatures;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            ->with('user', 'supplier', 'assignedUser');

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
            default:
                $digital_signatures->orderBy($default_sort, $order);
        }

        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        if (!empty($filter)) {
            $digital_signatures->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $digital_signatures->TextSearch($request->input('search'));
        }

        $total = $digital_signatures->count();
        $digital_signatures = $digital_signatures->skip($offset)->take($limit)->get();

        return (new DigitalSignaturesTransformer())->transformSignatures($digital_signatures, $total);
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
        $digitalSignatures->status_id = $request->get('status_id', config('enum.status_id.READY_TO_DEPLOY'));

        if (!$digitalSignatures->save()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $digitalSignatures->getErrors()));
        }

        if($request->get('assigned_to')){
            $digitalSignatures->checkOut(
                $assigned_to = $request->get('assigned_to'),
                $assigned_status = config('enum.assigned_status.WAITINGCHECKOUT'),
                $checkout_date = Carbon::now()->format('d-m-Y H:i:s'),
                $assigned_type = 'user'
            );
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
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $this->authorize('update', DigitalSignatures::class);
        $signature = DigitalSignatures::findOrFail($id);
        $signature->fill($request->all());
        if (!$signature->save()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $signature->getErrors()));
        }

        return response()->json(Helper::formatStandardApiResponse('success', $signature, trans('admin/digital_signatures/message.update.success', ['seri' => $signature->seri])));
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
     */
    public function checkout(Request $request, int $digital_signature_id)
    {
        $this->authorize('checkout', DigitalSignatures::class);
        $signature = DigitalSignatures::findOrFail($digital_signature_id);
        if (!$signature->availableForCheckout()) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error', 
                    ['asset'=> e($signature->seri)], 
                    trans('admin/digital_signatures/message.checkout.not_available'))
            );
        }
        $this->authorize('checkout', $signature);
        $assigned_to = $request->get('assigned_to');
        $signature->assigned_to = $assigned_to;
        $signature->status = config('enum.status_id.ASSIGN');
        $signature->checkout_date = $request->get('checkout_date');
        $request->get('note') ? $signature->note = $request->get('note') : null;
        if(!$signature->save()){
            return response()->json(Helper::formatStandardApiResponse('error', null, $signature->getErrors()));
        }
        return response()->json(Helper::formatStandardApiResponse('success', ['digital_signature' => e($signature->seri)], trans('admin/digital_signatures/message.checkout.success')));
    }
}
