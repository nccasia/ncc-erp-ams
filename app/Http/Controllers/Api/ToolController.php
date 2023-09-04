<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DateFormatter;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ToolCheckoutTransformer;
use App\Http\Transformers\ToolsTransformer;
use App\Jobs\SendCheckinMailTool;
use App\Jobs\SendCheckoutMailTool;
use App\Jobs\SendConfirmMailTool;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Tool;
use App\Models\ToolUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $tools = Tool::select('tools.*')->with('user', 'supplier', 'assignedUser', 'location', 'category', 'tokenStatus');
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
        $this->authorize('view', Software::class);

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

        return response()->json(Helper::formatStandardApiResponse('success', $total_detail, null));
    }

    public function filters($tools, $request)
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
        $tool->category_id = $request->get('category_id');
        $tool->location_id = $request->get('location_id');
        $tool->expiration_date = $request->get('expiration_date');
        $tool->supplier_id = $request->get('supplier_id');
        $tool->assigned_status = config('enum.assigned_status.DEFAULT');
        $tool->status_id = $request->get('status_id');
        $tool->qty = $request->get('qty');
        $tool->notes = $request->get('notes');
        $tool->user_id = Auth::id();

        if ($tool->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $tool, trans('admin/tools/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $tool->getErrors()), Response::HTTP_BAD_REQUEST);
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
        $assigned_status = $tool->assigned_status;
        $tool->fill($request->all());
        $user = null;
        if ($tool->assigned_to) {
            $user = User::find($tool->assigned_to);
        }

        if ($user && $request->has('assigned_status') && $assigned_status !== $request->get('assigned_status')) {
            $tool->assigned_status = $request->get('assigned_status');
            if ($tool->assigned_status == config('enum.assigned_status.ACCEPT')) {
                if ($tool->withdraw_from) {
                    $tool->increment('checkin_counter', 1);
                    $is_confirm = 'đã xác nhận thu hồi';
                    $subject = 'Mail xác nhận thu hồi tool';
                    $tool->status_id = config('enum.status_id.READY_TO_DEPLOY');
                    $tool->assigned_status = config('enum.assigned_status.DEFAULT');
                    $tool->withdraw_from = null;
                    $tool->last_checkout = null;
                    $tool->assigned_to = null;
                    $this->sendMailConfirm($user, $tool, $is_confirm, $subject);
                } else {
                    $tool->increment('checkout_counter', 1);
                    $is_confirm = 'đã xác nhận cấp phát';
                    $subject = 'Mail xác nhận cấp phát tool';
                    $tool->status_id = config('enum.status_id.ASSIGN');
                    $this->sendMailConfirm($user, $tool, $is_confirm, $subject);
                }
            } elseif ($tool->assigned_status == config('enum.assigned_status.REJECT')) {
                if ($tool->withdraw_from) {
                    $is_confirm = 'đã từ chối thu hồi';
                    $subject = 'Mail từ chối thu hồi tool';
                    $reason = 'Lý do: ' . $request->get('reason');
                    $tool->status_id = config('enum.status_id.ASSIGN');
                    $tool->assigned_status = config('enum.assigned_status.ACCEPT');
                    $this->sendMailConfirm($user, $tool, $is_confirm, $subject, $reason);
                } else {
                    $is_confirm = 'đã từ chối nhận';
                    $subject = 'Mail từ chối nhận tool';
                    $reason = 'Lý do: ' . $request->get('reason');
                    $tool->status_id = config('enum.status_id.READY_TO_DEPLOY');
                    $tool->assigned_status = config('enum.assigned_status.DEFAULT');
                    $tool->withdraw_from = null;
                    $tool->last_checkout = null;
                    $tool->assigned_to = null;;
                    $this->sendMailConfirm($user, $tool, $is_confirm, $subject, $reason);
                }
            }
        }

        if (!$tool->save()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $tool->getErrors()), Response::HTTP_BAD_REQUEST);
        }
        return response()->json(Helper::formatStandardApiResponse('success', $tool, trans('admin/tools/message.update.success')));
    }

    /**
     * update multiple tools
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function multiUpdate(Request $request)
    {
        $this->authorize('update', Tool::class);
        $tools = $request->get('tools');
        foreach ($tools as $id) {
            $tool = Tool::findOrFail($id);
            $assigned_status = $tool->assigned_status;
            $tool->fill($request->all());
            $user = null;
            if ($tool->assigned_to) {
                $user = User::find($tool->assigned_to);
            }
            if ($user && $request->has('assigned_status') && $assigned_status !== $request->get('assigned_status')) {
                $tool->assigned_status = $request->get('assigned_status');
                if ($tool->assigned_status == config('enum.assigned_status.ACCEPT')) {
                    if ($tool->withdraw_from) {
                        $tool->increment('checkin_counter', 1);
                        $is_confirm = 'đã xác nhận thu hồi';
                        $subject = 'Mail xác nhận thu hồi tool';
                        $tool->status_id = config('enum.status_id.READY_TO_DEPLOY');
                        $tool->assigned_status = config('enum.assigned_status.DEFAULT');
                        $tool->withdraw_from = null;
                        $tool->last_checkout = null;
                        $tool->assigned_to = null;
                        $this->sendMailConfirm($user, $tool, $is_confirm, $subject);
                    } else {
                        $tool->increment('checkout_counter', 1);
                        $is_confirm = 'đã xác nhận cấp phát';
                        $subject = 'Mail xác nhận cấp phát tool';
                        $tool->status_id = config('enum.status_id.ASSIGN');
                        $this->sendMailConfirm($user, $tool, $is_confirm, $subject);
                    }
                } elseif ($tool->assigned_status == config('enum.assigned_status.REJECT')) {
                    if ($tool->withdraw_from) {
                        $is_confirm = 'đã từ chối thu hồi';
                        $subject = 'Mail từ chối thu hồi tool';
                        $reason = 'Lý do: ' . $request->get('reason');
                        $tool->status_id = config('enum.status_id.ASSIGN');
                        $tool->assigned_status = config('enum.assigned_status.ACCEPT');
                        $this->sendMailConfirm($user, $tool, $is_confirm, $subject, $reason);
                    } else {
                        $is_confirm = 'đã từ chối nhận';
                        $subject = 'Mail từ chối nhận tool';
                        $reason = 'Lý do: ' . $request->get('reason');
                        $tool->status_id = config('enum.status_id.READY_TO_DEPLOY');
                        $tool->assigned_status = config('enum.assigned_status.DEFAULT');
                        $tool->withdraw_from = null;
                        $tool->last_checkout = null;
                        $tool->assigned_to = null;;
                        $this->sendMailConfirm($user, $tool, $is_confirm, $subject, $reason);
                    }
                }
            }
            if (!$tool->save()) {
                return response()->json(Helper::formatStandardApiResponse('error', null, $tool->getErrors()));
            }
        }

        return response()->json(Helper::formatStandardApiResponse('success', $tool, trans('admin/tools/message.update.success', ['signature' => "lol"])));
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
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/tools/message.does_not_exist')), Response::HTTP_BAD_REQUEST);
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
        if (!$tool->availableForCheckout()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/tools/message.checkout.not_available')),
                Response::HTTP_BAD_REQUEST
            );
        }
        $target = User::find($request->get('assigned_to'));
        $note = request('note', null);
        $checkout_date = $request->get('checkout_at');
        // $request->get('notes') ? $notes = $request->get('notes') : $notes = null;
        $tool->status_id = config('enum.status_id.ASSIGN');
        if ($tool->checkOut($target, $checkout_date, $tool->name, config('enum.assigned_status.WAITINGCHECKOUT'), $note)) {
            $this->sendMailCheckout($target, $tool);
            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    ['tool' => e($tool->name)],
                    trans('admin/tools/message.checkout.success')
                )
            );
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $tool->getErrors()), Response::HTTP_BAD_REQUEST);
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

        $tools = request('tools');
        $assigned_to = $request->get('assigned_to');
        $checkout_date = $request->get('checkout_date');
        $request->get('notes') ? $note = $request->get('notes') : $note = null;
        $target = User::find($request->get('assigned_to'));
        foreach ($tools as $tool_id) {
            $tool = Tool::findOrFail($tool_id);
            if (!$tool->availableForCheckout()) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        ['tool' => e($tool->name)],
                        trans('admin/tools/message.checkout.not_available')
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $tool->status_id = config('enum.status_id.ASSIGN');
            if ($tool->checkOut($target, $checkout_date, $tool->name, config('enum.assigned_status.WAITINGCHECKOUT'), $note)) {
                $this->sendMailCheckout($target, $tool);
            } else {
                return response()->json(
                    Helper::formatStandardApiResponse('error', null, trans('admin/tools/message.checkout.error')),
                    Response::HTTP_BAD_REQUEST
                );
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
    public function checkIn(Request $request, $tool_id)
    {
        $this->authorize('checkin', Tool::class);
        $tool = Tool::findOrFail($tool_id);
        if (is_null($target = $tool->assigned_to)) {
            return response()->json(Helper::formatStandardApiResponse('error', ['name' => e($tool->name)], trans('admin/tools/message.checkin.already_checked_in')));
        }
        if (!$tool->availableForCheckin()) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    ['tool' => e($tool->name)],
                    trans('admin/tools/message.checkin.not_available')
                )
            );
        }

        $checkin_date = $request->get('checkin_at');
        $request->get('notes') ? $note = $request->get('notes') : $note = null;
        $target = User::findOrFail($tool->assigned_to);

        if ($tool->checkIn($target, $checkin_date, $tool->name, config('enum.assigned_status.WAITINGCHECKIN'), $note)) {
            $this->sendMailCheckin($tool->assignedTo, $tool);
            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    ['tool' => e($tool->name)],
                    trans('admin/tools/message.checkin.success')
                )
            );
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $tool->getErrors()), Response::HTTP_BAD_REQUEST);
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

        $tools = request('tools');
        $checkin_date = $request->get('checkout_at');
        $request->get('notes') ? $note = $request->get('notes') : $note = null;

        foreach ($tools as $tool_id) {
            $tool = Tool::findOrFail($tool_id);
            if (is_null($target = $tool->assigned_to)) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        ['tool' => e($tool->name)],
                        trans('admin/tool/message.checkin.already_checked_in')
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }
            if (!$tool->availableForCheckin()) {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        ['assigned_users' => trans('admin/tools/message.checkout.error')]
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $checkin_date = $request->get('checkin_at');
            $request->get('notes') ? $note = $request->get('notes') : $note = null;
            $target = User::findOrFail($tool->assigned_to);

            if ($tool->checkIn($target, $checkin_date, $tool->name, config('enum.assigned_status.WAITINGCHECKIN'), $note)) {
                $this->sendMailCheckin($target, $tool);
            } else {
                return response()->json(
                    Helper::formatStandardApiResponse(
                        'error',
                        null,
                        trans('admin/tools/message.checkin.error')
                    ),
                    Response::HTTP_BAD_REQUEST
                );
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

        $total = $tools->count();
        $tools = $tools->skip($offset)->take($limit)->get();
        return (new ToolsTransformer)->transformTools($tools, $total);
    }

    /**
     * Send mail to user when checkout
     *
     * @param  User $assigned_user
     * @param  Tool $tool
     * @return void
     */
    public function sendMailCheckout($assigned_user, $tool)
    {
        $user_email = $assigned_user->email;
        $user_name = $assigned_user->first_name . ' ' . $assigned_user->last_name;
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
     * @param  User $assigned_user
     * @param  Tool $tool
     * @return void
     */
    public function sendMailCheckin($assigned_user, $tool)
    {
        $user_email = $assigned_user->email;
        $user_name = $assigned_user->first_name . ' ' . $assigned_user->last_name;
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

    /**
     * Send mail to user when checkout
     *
     * @param  User $assigned_user
     * @param  Tool $tool
     * @return void
     */
    public function sendMailConfirm($assigned_user, $tool, $is_confirm, $subject, $reason = "")
    {
        $it_ncc_email = Setting::first()->admin_cc_email;
        $user_name = $assigned_user->first_name . ' ' . $assigned_user->last_name;
        $current_time = Carbon::now();
        $data = [
            'user_name' => $user_name,
            'tool_name' => $tool->name,
            'tools_count' => 1,
            'is_confirm' => $is_confirm,
            'subject' => $subject,
            'reason' => $reason,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendConfirmMailTool::dispatch($data, $it_ncc_email);
    }
}
