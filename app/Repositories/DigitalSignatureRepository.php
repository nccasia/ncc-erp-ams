<?php

namespace App\Repositories;

use App\Exceptions\TaskReturnError;
use App\Helpers\DateFormatter;
use App\Models\DigitalSignatures;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class DigitalSignatureRepository
{
    public function index($request)
    {
        $digital_signatures = DigitalSignatures::select('digital_signatures.*')
            ->with('user', 'supplier', 'assignedUser', 'location', 'category', 'tokenStatus');
        $digital_signatures = $this->filters($digital_signatures, $request);
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

        $data = $this->getDataDigitalSignature($request, $digital_signatures, $allowed_columns);
        return $data;
    }

    public function getTotalDetail($request)
    {
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
        return [
            'status' => 'Success',
            'payload' => $total_detail,
            'message' => null
        ];
    }

    public function assign($request)
    {
        $user_id = Auth::id();
        $digital_signatures = DigitalSignatures::select('digital_signatures.*')->join('users', 'digital_signatures.assigned_to', 'users.id')
            ->where('users.id', $user_id)
            ->with('user', 'supplier', 'assignedUser', 'location', 'category', 'tokenStatus');
        $digital_signatures = $this->filters($digital_signatures, $request);

        $allowed_columns = [
            'id',
            'tool_id',
            'purchase_cost',
            'name',
            'category_id',
            'manufacturer_id',
            'notes',
            'purchase_date',
            'assinged_to',
            'checkout_at',
            'version'
        ];
        $data = $this->getDataDigitalSignature($request, $digital_signatures, $allowed_columns);
        return $data;
    }

    public function store($request)
    {
        $digitalSignatures = new DigitalSignatures();
        $digitalSignatures->user_id = Auth::id();
        $digitalSignatures->assigned_status = config('enum.status_tax_token.NOT_ACTIVE');
        $digitalSignatures->fill($request);

        if (!$digitalSignatures->save()) {
            throw new TaskReturnError('error', null, $digitalSignatures->getErrors(), Response::HTTP_BAD_REQUEST);
        }
        return $digitalSignatures;
    }

    public function show($id)
    {
        $digitalSignature = DigitalSignatures::with('user', 'supplier', 'assignedUser')
            ->findOrFail($id);
        return $digitalSignature;
    }

    public function update($request, $id, $type)
    {
        $digitalSignature = DigitalSignatures::find($id);
        $digitalSignature->fill($request);

        if (Arr::except($request,'assigned_status')) {
            $digitalSignature->assigned_status = $request['assigned_status'];
        }

        switch ($type) {
            case config('enum.update_type.ACCEPT_CHECKOUT'):
                $digitalSignature->increment('checkout_counter', 1);
                $digitalSignature->status_id = config('enum.status_id.ASSIGN');
                break;
            case config('enum.update_type.ACCEPT_CHECKIN'):
                $digitalSignature->increment('checkin_counter', 1);
                $digitalSignature->status_id = config('enum.status_id.READY_TO_DEPLOY');
                $digitalSignature->assigned_status = config('enum.assigned_status.DEFAULT');
                $digitalSignature->withdraw_from = null;
                $digitalSignature->last_checkout = null;
                $digitalSignature->assigned_to = null;
                break;
            case config('enum.update_type.REJECT_CHECKOUT'):
                $digitalSignature->status_id = config('enum.status_id.READY_TO_DEPLOY');
                $digitalSignature->assigned_status = config('enum.assigned_status.DEFAULT');
                $digitalSignature->withdraw_from = null;
                $digitalSignature->last_checkout = null;
                $digitalSignature->assigned_to = null;
                break;
            case config('enum.update_type.REJECT_CHECKIN'):
                $digitalSignature->status_id = config('enum.status_id.ASSIGN');
                $digitalSignature->assigned_status = config('enum.assigned_status.ACCEPT');
                break;
            default:
                break;
        }

        if (!$digitalSignature->save()) {
            throw new TaskReturnError('error', null, $digitalSignature->getErrors(), Response::HTTP_BAD_REQUEST);
        }
        return $digitalSignature;
    }

    public function delete($id)
    {
        $digitalSignature = DigitalSignatures::findOrFail($id);
        $res = $digitalSignature->delete();
        if(!$res) {
            throw new TaskReturnError(
                'error', 
                null, 
                trans('admin/tools/message.does_not_exist'), 
                Response::HTTP_BAD_REQUEST
            );
        }

        return $res;
    }

    public function getDigitalSignatureById($id)
    {
        $digitalSignature = DigitalSignatures::findOrFail($id);
        return $digitalSignature;
    }

    private function filters($digital_signatures, $request)
    {
        $filter = [];

        if (Arr::exists($request, 'filter')) {
            $filter = json_decode($request['filter'], true);
        }

        if (Arr::exists($request, 'supplier')) {
            $digital_signatures->InSupplier($request['supplier']);
        }

        if (Arr::exists($request, 'status_label')) {
            $digital_signatures->InStatus($request['status_label']);
        }

        if (Arr::exists($request, 'assigned_status')) {
            $digital_signatures->InAssignedStatus($request['assigned_status']);
        }

        if (Arr::exists($request, 'location_id')) {
            $digital_signatures->where('digital_signatures.location_id', '=', $request['location_id']);
        }

        if (Arr::exists($request, 'supplier_id')) {
            $digital_signatures->where('supplier_id', '=', $request['supplier_id']);
        }

        if (Arr::exists($request, 'manufacture_id')) {
            $digital_signatures->where('manufacture_id', '=', $request['manufacture_id']);
        }

        if (Arr::exists($request, 'category_id')) {
            $digital_signatures->where('category_id', '=', $request['category_id']);
        }

        if (Arr::exists($request, 'user_list')) {
            $digital_signatures->where('assigned_to', '=', Auth::user()->id);
        }

        if (Arr::exists($request, 'WAITING_CHECKOUT') || Arr::exists($request, 'WAITING_CHECKIN')) {
            $digital_signatures->where(function ($query) use ($request) {
                $query->where('digital_signatures.assigned_status', '=', $request['WAITING_CHECKOUT'])
                    ->orWhere('digital_signatures.assigned_status', '=', $request['WAITING_CHECKIN']);
            });
        }

        if (Arr::exists($request, 'purchaseDateFrom') && Arr::exists($request, 'purchaseDateTo')) {
            $filterByDate = DateFormatter::formatDate($request['purchaseDateFrom'], $request['purchaseDateTo']);
            $digital_signatures->whereBetween('digital_signatures.purchase_date', [$filterByDate]);
        }
        if (Arr::exists($request, 'expirationDateFrom') && Arr::exists($request, 'expirationDateTo')) {
            $filterByDate = DateFormatter::formatDate($request['expirationDateFrom'], $request['expirationDateTo']);
            $digital_signatures->whereBetween('digital_signatures.expiration_date', [$filterByDate]);
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $digital_signatures->ByFilter($filter);
        } elseif (Arr::exists($request, 'search')) {
            $digital_signatures->TextSearch($request['search']);
        }

        return $digital_signatures;
    }

    private function getDataDigitalSignature($request, $digital_signatures, $allowed_columns)
    {
        $offset =  ($digital_signatures && ($request['offset'] > $digital_signatures->count()))
            ? $digital_signatures->count()
            : $request['offset'] ?? 0;

        $limit = ((config('app.max_results') >= $request['limit']) && Arr::exists($request, 'limit'))
            ? $limit = $request['limit']
            : $limit = config('app.max_results');

        $order = $request['order'] === 'asc' ? 'asc' : 'desc';
        $sort = $request['sort'];

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

        $data['total'] = $digital_signatures->count();
        $data['digital_signatures'] = $digital_signatures->skip($offset)->take($limit)->get();

        return $data;
    }
}
