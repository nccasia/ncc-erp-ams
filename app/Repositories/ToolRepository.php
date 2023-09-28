<?php

namespace App\Repositories;

use App\Helpers\DateFormatter;
use Illuminate\Http\Request;
use App\Models\Tool;
use Illuminate\Support\Facades\Auth;

class ToolRepository
{
    public function index(Request $request)
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

    public function getTotalDetail(Request $request)
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

    public function getToolAssignList(Request $request)
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

    public function store(Request $request)
    {
        $tool = new Tool();

        $data = $request->input();
        $data['user_id'] = Auth::id();
        $tool->assigned_status = config('enum.assigned_status.DEFAULT');
        $tool->fill($data);

        if ($tool->save()) {
            return ['isSuccess' => true, 'data' => $tool];
        } else {
            return ['isSuccess' => false, 'data' => $tool->getErrors()];
        }
    }

    public function update(Request $request, $id, $type)
    {
        $tool = Tool::find($id);
        $tool->fill($request->all());

        if ($request->has('assigned_status')) {
            $tool->assigned_status = $request->get('assigned_status');
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

        if ($tool->save()) {
            return ['isSuccess' => true, 'data' => $tool];
        } else {
            return ['isSuccess' => false, 'data' => $tool->getErrors()];
        }
    }

    public function delete($id)
    {
        $tool = Tool::findOrFail($id);

        return $tool->delete();
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
        if ($request->filled('location_id')) {
            $tools->where('tools.location_id', '=', $request->input('location_id'));
        }

        if ($request->filled('category_id')) {
            $tools->where('category_id', '=', $request->input('category_id'));
        }

        if ($request->filled('supplier_id')) {
            $tools->where('supplier_id', '=', $request->input('supplier_id'));
        }

        if ($request->filled('manufacture_id')) {
            $tools->where('manufacture_id', '=', $request->input('manufacture_id'));
        }

        $filter = [];
        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $tools->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $tools->TextSearch($request->input('search'));
        }

        if ($request->filled('supplier_id')) {
            $tools->BySupplier($request->input('supplier_id'));
        }

        if ($request->status_label) {
            $tools->InStatus($request->input('status_label'));
        }

        if ($request->filled('assigned_status')) {
            $tools->InAssignedStatus($request->input('assigned_status'));
        }

        if ($request->filled('purchaseDateFrom', 'purchaseDateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('purchaseDateFrom'), $request->input('purchaseDateTo'));
            $tools->whereBetween('tools.purchase_date', [$filterByDate]);
        }

        if ($request->filled('expirationDateFrom', 'expirationDateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('expirationDateFrom'), $request->input('expirationDateTo'));
            $tools->whereBetween('tools.expiration_date', [$filterByDate]);
        }

        if ($request->filled('WAITING_CHECKOUT') || $request->filled('WAITING_CHECKIN')) {
            $tools->where(function ($query) use ($request) {
                $query->where('tools.assigned_status', '=', $request->input('WAITING_CHECKOUT'))
                    ->orWhere('tools.assigned_status', '=', $request->input('WAITING_CHECKIN'));
            });
        }
        return $tools;
    }

    private function getDataTools($request, $tools, $allowed_columns)
    {
        $offset = (($tools) && ($request->get('offset') > $tools->count()))
            ? $tools->count()
            : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        $sort = $request->input('sort');

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
