<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DateFormatter;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ToolCheckoutTransformer;
use App\Http\Transformers\ToolsTransformer;
use App\Jobs\SendCheckinMailTool;
use App\Jobs\SendCheckoutMailTool;
use App\Models\Company;
use App\Models\Tool;
use App\Models\ToolUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ToolController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request 
     * @return array
     */
    public function index(Request $request)
    {
        $this->authorize('view', Software::class);

        // $tools = Company::scopeCompanyables(
        //     Tool::select('tools.*', 'users.username')
        //         ->join('users', 'users.id', 'tools.user_id')
        //         ->with('category', 'supplier', 'location')
        //         ->withCount([
        //             'toolsUsers' => function ($query) {
        //                 $query->whereNotNull('checkout_at')->whereNull('checkin_at');
        //             }
        //         ])
        // );

        $tools = Tool::select('tools.*')->with('user', 'supplier', 'assignedUser', 'location', 'category', 'tokenStatus');

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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', Tool::class);

        $tool = new Tool();

        $tool->name = $request->get('name');
        $tool->purchase_cost = $request->get('purchase_cost');
        $tool->purchase_date = $request->get('purchase_date');
        $tool->version = $request->get('version');
        $tool->category_id = $request->get('category_id');
        $tool->manufacturer_id = $request->get('manufacturer_id');
        $tool->notes = $request->get('notes');
        $tool->user_id = Auth::id();

        if ($tool->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $tool, trans('admin/tools/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $tool->getErrors()));
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
        $this->authorize('update', Tool::class);

        $tool = Tool::find($id);
        if ($tool) {
            $tool->fill($request->all());
            if ($tool->save()) {
                return response()->json(Helper::formatStandardApiResponse('success', $tool, trans('admin/tools/message.update.success')));
            }
            return response()->json(Helper::formatStandardApiResponse('error', null, $tool->getErrors()));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/tools/message.does_not_exist')));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $tool = Tool::findOrFail($id);

        $this->authorize('delete', $tool);

        if ($tool->delete()) {
            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/tools/message.delete.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/tools/message.does_not_exist')));
    }

    /**
     * Checkout a tool to users
     *
     * @param  int  $tool_id
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Request $request, $tool_id)
    {
        $this->authorize('checkout', Tool::class);

        $tool = Tool::findOrFail($tool_id);
        $assigned_users = $request->get('assigned_users');

        //Check tool already checkout to user or not
        foreach ($assigned_users as $assigned_user) {
            $tool_user = $tool->toolsUsers()
                ->where('tool_id', $tool_id)
                ->where('assigned_to', $assigned_user)
                ->whereNull('checkin_at')->first();
            if ($tool_user) {
                $user = User::find($tool_user->assigned_to);
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        ['assigned_users' => $tool->name . trans('admin/tools/message.checkout.already_user') . $user->username]
                    )
                );
            }
        }

        foreach ($assigned_users as $assigned_user) {
            if (User::find($assigned_user)) {
                $tool_user = $this->setDataCheckout($tool, $assigned_user, $request);
                if ($tool_user->save()) {
                    $this->sendMailCheckOut($assigned_user, $tool);
                } else {
                    return response()->json(Helper::formatStandardApiResponse('error', null, $tool_user->getErrors()));
                }
            }
        }

        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                ['tool' => e($tool_user->tool->name)],
                trans('admin/licenses/message.checkout.success')
            )
        );
    }

    /**
     * Checkout multiple tools to users
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function multiCheckout(Request $request)
    {
        $this->authorize('checkout', Tool::class);

        $tools_id = $request->get('tools');
        $assigned_users = $request->get('assigned_users');

        foreach ($tools_id as $tool_id) {

            //Check tool already checkout to user or not
            $tool = Tool::find($tool_id);
            foreach ($assigned_users as $assigned_user) {
                $tool_user = $tool->toolsUsers()
                    ->where('tool_id', $tool_id)
                    ->where('assigned_to', $assigned_user)
                    ->whereNull('checkin_at')->first();
                if ($tool_user) {
                    $user = User::find($tool_user->assigned_to);
                    return response()->json(
                        Helper::formatStandardApiResponse(
                            'error',
                            null,
                            ['assigned_users' => $tool->name . trans('admin/tools/message.checkout.already_user') . $user->username]
                        )
                    );
                }
            }
        }

        foreach ($tools_id as $tool_id) {
            $tool = Tool::find($tool_id);
            foreach ($assigned_users as $assigned_user) {
                if (User::find($assigned_user)) {
                    $tool_user = $this->setDataCheckout($tool, $assigned_user, $request);
                    if ($tool_user->save()) {
                        $this->sendMailCheckOut($assigned_user, $tool);
                    } else {
                        return response()->json(Helper::formatStandardApiResponse('error', null, $tool_user->getErrors()));
                    }
                }
            }
        }
        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                null,
                trans('admin/tools/message.checkout.success')
            )
        );
    }

    /**
     * Checkin a tool 
     *
     * @param  int  $tool_id
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkin(Request $request, $tool_id)
    {
        $this->authorize('checkin', Tool::class);

        $tool = Tool::findOrFail($tool_id);
        $assigned_user = $request->get('assigned_user');

        //Check tool available for checkin
        if (!$tool->availableForCheckin($assigned_user)) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    null,
                    ['assigned_users' => trans('admin/tools/message.checkout.error')]
                )
            );
        }

        if (User::find($assigned_user)) {
            $tool_user = $this->setDataCheckin($tool, $assigned_user, $request);
            if ($tool_user->save()) {
                $this->sendMailCheckin($assigned_user, $tool);
            } else {
                return response()->json(Helper::formatStandardApiResponse('error', null, $tool_user->getErrors()));
            }
        }
        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                ['tool' => e($tool_user->tool->name)],
                trans('admin/tools/message.checkin.success')
            )
        );
    }

    /**
     * Checkin multiple tools 
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function multiCheckin(Request $request)
    {
        $this->authorize('checkin', Tool::class);

        $tools_id = $request->get('tools');
        $assigned_users = $request->get('assigned_users');

        // Check tools are available for checkin
        for ($i = 0; $i < count($tools_id); $i++) {
            $tool = Tool::find($tools_id[$i]);
            if (!$tool->availableForCheckin($assigned_users[$i])) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        ['assigned_users' => trans('admin/tools/message.checkout.error')]
                    )
                );
            }
        }

        for ($i = 0; $i < count($tools_id); $i++) {
            $tool = Tool::find($tools_id[$i]);
            if (User::find($assigned_users[$i])) {
                $tool_user = $this->setDataCheckin($tool, $assigned_users[$i], $request);
                if ($tool_user->save()) {
                    $this->sendMailCheckin($assigned_users[$i], $tool);
                } else {
                    return response()->json(Helper::formatStandardApiResponse('error', null, $tool_user->getErrors()));
                }
            }
        }
        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                null,
                trans('admin/tools/message.checkin.success')
            )
        );
    }

    /**
     * Get list tools already checkout
     *
     * @param  Request  $request
     * @return array
     */
    public function getToolsCheckout(Request $request)
    {
        $this->authorize('view', Tool::class);

        $tools_users = Company::scopeCompanyables(
            ToolUser::select('tools_users.*')
                ->whereNotNull('checkout_at')
                ->whereNull('checkin_at')
                ->with('tool', 'user')
        );

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

        $filter = [];
        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $tools_users->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $tools_users->TextSearch($request->input('search'));
        }

        if ($request->filled('manufacturer_id')) {
            $tools_users->ByManufacturer($request->input('manufacturer_id'));
        }

        $offset = (($tools_users) && ($request->get('offset') > $tools_users->count()))
            ? $tools_users->count()
            : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        if ($request->filled('dateFrom', 'dateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('dateFrom'), $request->input('dateTo'));
            $tools_users->join('tools', 'tools_users.tool_id', 'tools.id')->whereBetween('tools.created_at', [$filterByDate]);
        }

        $sort = $request->input('sort');

        $default_sort = in_array($sort, $allowed_columns) ? $sort : 'tools_users.created_at';

        switch ($sort) {
            case 'category':
                $tools_users->OrderCategory($order);
                break;
            case 'manufacturer':
                $tools_users->OrderManufacturer($order);
                break;
            case 'checkout_at':
                $tools_users->OrderBy($sort, $order);
                break;
            case 'id':
                $tools_users->OrderBy($sort, $order);
                break;
            case 'assigned_to':
                $tools_users->OrderAssingedTo($order);
                break;
            default:
                $tools_users->join('tools', 'tools_users.tool_id', 'tools.id')->OrderBy($default_sort, $order);
        }

        $total = $tools_users->count();
        $tools_users = $tools_users->skip($offset)->take($limit)->get();
        return (new ToolCheckoutTransformer)->transformToolsCheckout($tools_users, $total);
    }

    /**
     * Get list tools of user
     *
     * @param  Request  $request
     * @return array
     */
    public function assign(Request $request)
    {
        $this->authorize('view', Tool::class);

        $user_id = Auth::id();

        $tools = Company::scopeCompanyables(
            Tool::select('tools.*', 'tools_users.*')
                ->join('tools_users', 'tools.id', 'tools_users.tool_id')
                ->whereNotNull('tools_users.checkout_at')
                ->whereNull('tools_users.checkin_at')
                ->where('tools_users.assigned_to', $user_id)
                ->withCount([
                    'toolsUsers' => function ($query) {
                        $query->whereNotNull('checkout_at')->whereNull('checkin_at');
                    }
                ])
        );

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

    /**
     * Set data checkout
     *
     * @param  Tool $tool
     * @param  int $assigned_user
     * @param  Request $request
     * @return ToolUser
     */
    public function setDataCheckout($tool, $assigned_user, $request)
    {
        $tool_user = new ToolUser();
        $tool_user->tool_id = $tool->id;
        $tool_user->assigned_to = $assigned_user;
        $tool_user->checkout_at = $request->input('checkout_at');
        $tool_user->checkin_at = null;
        $tool_user->notes = $request->input('notes');
        $tool_user->created_at = Carbon::now();
        return $tool_user;
    }

    /**
     * Set data checkout
     *
     * @param  Tool $tool
     * @param  int $assigned_user
     * @param  Request $request
     * @return ToolUser
     */
    public function setDataCheckin($tool, $assigned_user, $request)
    {
        $tool_user = ToolUser::where('tool_id', $tool->id)->where('assigned_to', $assigned_user)->whereNull('checkin_at')->first();
        $tool_user->tool_id = $tool->id;
        $tool_user->assigned_to = $assigned_user;
        $tool_user->checkin_at = $request->input('checkin_at');
        $tool_user->notes = $request->input('notes');
        $tool_user->updated_at = Carbon::now();
        return $tool_user;
    }

    /**
     * Get data tools
     *
     * @param  mixed $tools
     * @param  array $allowed_columns
     * @param  Request $request
     * @return array
     */
    public function getDataTools($request, $tools, $allowed_columns)
    {
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

        $offset = (($tools) && ($request->get('offset') > $tools->count()))
            ? $tools->count()
            : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        if ($request->filled('dateFrom', 'dateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('dateFrom'), $request->input('dateTo'));
            $tools->whereBetween('tools.created_at', [$filterByDate]);
        }

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
            // case 'checkout_count':
            //     $tools->OrderBy('tools_users_count', $order);
            //     break;
            default:
                $tools->OrderBy($default_sort, $order);
        }

        if ($request->filled('WAITING_CHECKOUT') || $request->filled('WAITING_CHECKIN')) {
            $tools->where(function ($query) use ($request) {
                $query->where('tools.assigned_status', '=', $request->input('WAITING_CHECKOUT'))
                    ->orWhere('tools.assigned_status', '=', $request->input('WAITING_CHECKIN'));
            });
        }

        $total = $tools->count();
        $tools = $tools->skip($offset)->take($limit)->get();
        return (new ToolsTransformer)->transformTools($tools, $total);
    }

    /**
     * Send mail to user when checkout
     *
     * @param  int $assigned_user
     * @param  Tool $tool
     * @return void
     */
    public function sendMailCheckout($assigned_user, $tool)
    {
        $user = User::find($assigned_user);
        $user_email = $user->email;
        $user_name = $user->first_name . ' ' . $user->last_name;
        $current_time = Carbon::now();
        $data = [
            'user_name' => $user_name,
            'tool_name' => $tool->name,
            'count' => 1,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendCheckoutMailTool::dispatch($data, $user_email);
    }

    /**
     * Send mail to user when checkout
     *
     * @param  int $assigned_user
     * @param  Tool $tool
     * @return void
     */
    public function sendMailCheckin($assigned_user, $tool)
    {
        $user = User::find($assigned_user);
        $user_email = $user->email;
        $user_name = $user->first_name . ' ' . $user->last_name;
        $current_time = Carbon::now();
        $data = [
            'user_name' => $user_name,
            'tool_name' => $tool->name,
            'count' => 1,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendCheckinMailTool::dispatch($data, $user_email);
    }
}
