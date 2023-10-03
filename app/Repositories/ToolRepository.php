<?php

namespace App\Repositories;

use App\Exceptions\TaskReturnError;
use App\Helpers\DateFormatter;
use App\Models\Tool;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class ToolRepository
{
    public function index($request)
    {
        $tools = Tool::select('tools.*')
            ->with(
                'user',
                'supplier',
                'assignedUser',
                'location',
                'category',
                'tokenStatus'
            );
        $tools = $this->filters($tools, $request);

        $allowed_columns = [
            'name',
            'category_id',
            'supplier_id',
            'user_id',
            'purchase_cost',
            'purchase_date',
            'notes',
            'assisgned_to',
            'qty',
            'location_id',
            'status_id',
        ];

        return $this->getDataTools($request, $tools, $allowed_columns);
    }

    public function getTotalDetail($request)
    {
        $tools = Tool::select('tools.*')->with('user', 'supplier', 'assignedUser', 'location', 'category', 'tokenStatus');
        $tools = $this->filters($tools, $request);

        $total_tool_by_category = $tools->selectRaw('c.name as name , count(*) as total')
            ->join('categories as c', 'c.id', '=', 'tools.category_id')
            ->groupBy('c.name')
            ->pluck('total', 'c.name');
        $total_detail = $total_tool_by_category->map(function ($value, $key) {
            return [
                'name' => $key,
                'total' => $value
            ];
        })->values()->toArray();

        return $total_detail;
    }

    public function getToolAssignList($request)
    {
        $user_id = Auth::id();

        $tools = Tool::select('tools.*')->join('users', 'tools.assigned_to', 'users.id')
            ->where('users.id', $user_id)
            ->with('user', 'supplier', 'assignedUser', 'location', 'category', 'tokenStatus');
        $tools = $this->filters($tools, $request);

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
        return $this->getDataTools($request, $tools, $allowed_columns);
    }

    public function store($request)
    {
        $tool = new Tool();

        $data = $request;
        $data['user_id'] = Auth::id();
        $tool->assigned_status = config('enum.assigned_status.DEFAULT');
        $tool->fill($data);

        if (!$tool->save()) {
            throw new TaskReturnError(
                'error',
                null,
                $tool->getErrors(),
                Response::HTTP_BAD_REQUEST
            );
        } 
        return $tool;
    }

    public function update($request, $id, $type)
    {
        $tool = Tool::find($id);
        $tool->fill($request);

        if (Arr::exists($request,'assigned_status')) {
            $tool->assigned_status = $request['assigned_status'];
        }

        switch ($type) {
            case config('enum.update_type.ACCEPT_CHECKOUT'):
                $tool->increment('checkout_counter', 1);
                $tool->status_id = config('enum.status_id.ASSIGN');
                break;
            case config('enum.update_type.ACCEPT_CHECKIN'):
                $tool->increment('checkin_counter', 1);
                $tool->status_id = config('enum.status_id.READY_TO_DEPLOY');
                $tool->assigned_status = config('enum.assigned_status.DEFAULT');
                $tool->withdraw_from = null;
                $tool->last_checkout = null;
                $tool->assigned_to = null;
                break;
            case config('enum.update_type.REJECT_CHECKOUT'):
                $tool->status_id = config('enum.status_id.READY_TO_DEPLOY');
                $tool->assigned_status = config('enum.assigned_status.DEFAULT');
                $tool->withdraw_from = null;
                $tool->last_checkout = null;
                $tool->assigned_to = null;
                break;
            case config('enum.update_type.REJECT_CHECKIN'):
                $tool->status_id = config('enum.status_id.ASSIGN');
                $tool->assigned_status = config('enum.assigned_status.ACCEPT');
                break;
            default:
                break;
        }

        if (!$tool->save()) {
            throw new TaskReturnError(
                'error',
                null,
                $tool->getErrors(),
                Response::HTTP_BAD_REQUEST
            );
        } 
        return $tool;
    }

    public function delete($id)
    {
        $tool = Tool::findOrFail($id);

        $res = $tool->delete();
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

    public function getToolById($id)
    {
        $tool = Tool::find($id);
        return $tool;
    }

    public function getToolOrFailById($id)
    {
        $tool = Tool::findOrFail($id);
        return $tool;
    }

    private function filters($tools, $request)
    {
        if (Arr::exists($request,'location_id')) {
            $tools->where('tools.location_id', '=', $request['location_id']);
        }

        if (Arr::exists($request,'category_id')) {
            $tools->where('category_id', '=', $request['category_id']);
        }

        if (Arr::exists($request,'supplier_id')) {
            $tools->where('supplier_id', '=', $request['supplier_id']);
        }

        if (Arr::exists($request,'manufacture_id')) {
            $tools->where('manufacture_id', '=', $request['manufacture_id']);
        }

        $filter = [];
        if (Arr::exists($request,'filter')) {
            $filter = json_decode($request['filter'], true);
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $tools->ByFilter($filter);
        } elseif (Arr::exists($request,'search')) {
            $tools->TextSearch($request['search']);
        }

        if (Arr::exists($request,'supplier_id')) {
            $tools->BySupplier($request['supplier_id']);
        }

        if (Arr::exists($request,'status_label')) {
            $tools->InStatus($request['status_label']);
        }

        if (Arr::exists($request,'assigned_status')) {
            $tools->InAssignedStatus($request['assigned_status']);
        }

        if (Arr::exists($request,'purchaseDateFrom', 'purchaseDateTo')) {
            $filterByDate = DateFormatter::formatDate($request['purchaseDateFrom'], $request['purchaseDateTo']);
            $tools->whereBetween('tools.purchase_date', [$filterByDate]);
        }

        if (Arr::exists($request,'expirationDateFrom', 'expirationDateTo')) {
            $filterByDate = DateFormatter::formatDate($request['expirationDateFrom'], $request['expirationDateTo']);
            $tools->whereBetween('tools.expiration_date', [$filterByDate]);
        }

        if (Arr::exists($request,'WAITING_CHECKOUT') || Arr::exists($request,'WAITING_CHECKIN')) {
            $tools->where(function ($query) use ($request) {
                $query->where('tools.assigned_status', '=', $request['WAITING_CHECKOUT'])
                    ->orWhere('tools.assigned_status', '=', $request['WAITING_CHECKIN']);
            });
        }
        return $tools;
    }

    private function getDataTools($request, $tools, $allowed_columns)
    {
        $request_offset = $request['offset'] ? $request['offset'] : 0;
        $offset = (($tools) && ($request_offset > $tools->count()))
            ? $tools->count()
            : $request_offset;

        ((config('app.max_results') >= $request['limit']) && (Arr::exists($request,'limit')))
            ? $limit = $request['limit']
            : $limit = config('app.max_results');

        if (Arr::exists($request,'order')) {
            $order = $request['order'] === 'asc' ? 'asc' : 'desc';
        } else {
            $order = 'asc';
        }
        

        $sort = $request['sort'] ?? 'id';

        $default_sort = in_array($sort, $allowed_columns) ? $sort : 'tools.created_at';

        switch ($sort) {
            case 'user':
                $tools->orderUser($order);
                break;
            case 'assigned_to':
                $tools->orderAssignToUser($order);
                break;
            case 'supplier':
                $tools->OrderSupplier($order);
                break;
            case 'location':
                $tools->OrderLocation($order);
                break;
            case 'category':
                $tools->OrderCategory($order);
                break;
            default:
                $tools->OrderBy($default_sort, $order);
        }

        $data['total'] = $tools->count();
        $data['tools'] = $tools->skip($offset)->take($limit)->get();
        return $data;
    }
}
