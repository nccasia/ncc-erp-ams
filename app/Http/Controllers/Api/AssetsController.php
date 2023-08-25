<?php

namespace App\Http\Controllers\Api;

use App\Events\CheckoutableCheckedIn;
use App\Helpers\DateFormatter;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetCheckoutRequest;
use App\Http\Transformers\AssetsTransformer;
use App\Http\Transformers\DepreciationReportTransformer;
use App\Http\Transformers\LicensesTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\CustomField;
use App\Models\License;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\ImageUploadRequest;
use Input;
use Paginator;
use Slack;
use Str;
use TCPDF;
use Validator;
use Route;
use App\Jobs\SendCheckoutMail;
use App\Jobs\SendConfirmMail;
use App\Jobs\SendConfirmRevokeMail;
use App\Models\AssetHistory;
use App\Models\AssetHistoryDetail;
use App\Jobs\SendCheckinMail;
use App\Http\Requests\AssetCheckinRequest;
use App\Jobs\SendRejectAllocateMail;
use App\Jobs\SendRejectRevokeMail;
use App\Models\Category;

/**
 * This class controls all actions related to assets for
 * the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 * @author [A. Gianotto] [<snipe@snipe.net>]
 */

class AssetsController extends Controller
{
    /**
     * Returns JSON listing of all assets
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param int $assetId
     * @since [v4.0]
     * @return JsonResponse
     */
    public function index(Request $request, $audit = null)
    {
        \Log::debug(Route::currentRouteName());
        $filter_non_deprecable_assets = false;

        /**
         * This looks MAD janky (and it is), but the AssetsController@index does a LOT of heavy lifting throughout the 
         * app. This bit here just makes sure that someone without permission to view assets doesn't 
         * end up with priv escalations because they asked for a different endpoint. 
         * 
         * Since we never gave the specification for which transformer to use before, it should default 
         * gracefully to just use the AssetTransformer by default, which shouldn't break anything. 
         * 
         * It was either this mess, or repeating ALL of the searching and sorting and filtering code, 
         * which would have been far worse of a mess. *sad face*  - snipe (Sept 1, 2021)
         */
        if (Route::currentRouteName() == 'api.depreciation-report.index') {
            $filter_non_deprecable_assets = true;
            $transformer = 'App\Http\Transformers\DepreciationReportTransformer';
            $this->authorize('reports.view');
        } else {
            $transformer = 'App\Http\Transformers\AssetsTransformer';
            $this->authorize('index', Asset::class);
        }


        $settings = Setting::getSettings();

        $allowed_columns = [
            'id',
            'name',
            'asset_tag',
            'serial',
            'model_number',
            'last_checkout',
            'notes',
            'expected_checkin',
            'order_number',
            'image',
            'assigned_to',
            'created_at',
            'updated_at',
            'purchase_date',
            'purchase_cost',
            'last_audit_date',
            'next_audit_date',
            'assigned_status',
            'requestable',
            'warranty_months',
            'checkout_counter',
            'checkin_counter',
            'requests_counter',
        ];

        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        $all_custom_fields = CustomField::all(); //used as a 'cache' of custom fields throughout this page load
        foreach ($all_custom_fields as $field) {
            $allowed_columns[] = $field->db_column_name();
        }

        $assets = Company::scopeCompanyables(Asset::select('assets.*'), 'company_id', 'assets')
            ->with(
                'location',
                'assetstatus',
                'company',
                'defaultLoc',
                'assignedTo',
                'model.category',
                'model.manufacturer',
                'model.fieldset',
                'supplier'
            ); //it might be tempting to add 'assetlog' here, but don't. It blows up update-heavy users.

        $assets->filterAssetByRole($request->user());
        // if ($request->filled('type')) {
        //     $type = $request->filled('type');

        //     $assets = $assets->whereHas('asset_history_details', function ($q) use ($type) {
        //         $q->whereRaw();
        //     });
        // }

        if ($filter_non_deprecable_assets) {
            $non_deprecable_models = AssetModel::select('id')->whereNotNull('depreciation_id')->get();

            $assets->InModelList($non_deprecable_models->toArray());
        }

        // These are used by the API to query against specific ID numbers.
        // They are also used by the individual searches on detail pages like
        // locations, etc.


        // Search custom fields by column name
        foreach ($all_custom_fields as $field) {
            if ($request->filled($field->db_column_name())) {
                $assets->where($field->db_column_name(), '=', $request->input($field->db_column_name()));
            }
        }

        if ($request->filled('assigned_status')) {
            $assets->InAssignedStatus($request->input('assigned_status'));
        }

        if ($request->filled('WAITING_CHECKOUT') || $request->filled('WAITING_CHECKIN')) {
            $assets->where(function ($query) use ($request) {
                $query->where('assets.assigned_status', '=', $request->input('WAITING_CHECKOUT'))
                    ->orWhere('assets.assigned_status', '=', $request->input('WAITING_CHECKIN'));
            });
        }

        if ($request->filled('status_id')) {
            $assets->where('assets.status_id', '=', $request->input('status_id'));
        }

        if ($request->input('requestable') == 'true') {
            $assets->where('assets.requestable', '=', '1');
        }

        if ($request->filled('model_id')) {
            $assets->InModelList([$request->input('model_id')]);
        }

        if ($request->filled('category_id')) {
            $assets->InCategory($request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            $assets->where('assets.location_id', '=', $request->input('location_id'));
        }

        if ($request->filled('dateFrom', 'dateTo')) {
            $assets
                ->whereBetween('assets.purchase_date', [$request->input('dateFrom'), $request->input('dateTo')]);
        }

        if ($request->filled('dateCheckoutFrom', 'dateCheckoutTo')) {
            $filterByCheckoutDate = DateFormatter::formatDate($request->input('dateCheckoutFrom'), $request->input('dateCheckoutTo'));
            $assets
                ->whereBetween('assets.last_checkout', [$filterByCheckoutDate]);
        }

        if ($request->filled('rtd_location_id')) {
            $assets->where('assets.rtd_location_id', '=', $request->input('rtd_location_id'));
        }

        if ($request->filled('supplier_id')) {
            $assets->where('assets.supplier_id', '=', $request->input('supplier_id'));
        }

        if (($request->filled('assigned_to')) && ($request->filled('assigned_type'))) {
            $assets->where('assets.assigned_to', '=', $request->input('assigned_to'))
                ->where('assets.assigned_type', '=', $request->input('assigned_type'));
        }

        if ($request->filled('company_id')) {
            $assets->where('assets.company_id', '=', $request->input('company_id'));
        }

        if ($request->category) {
            $assets->InCategory($request->input('category'));
        }

        // if ($request->status_label) {
        //     $assets->where('assets.status_id', '=', $request->input('status_label'));
        // }

        if ($request->status_label) {
            $assets->InStatus($request->input('status_label'));
        }

        if ($request->filled('manufacturer_id')) {
            $assets->ByManufacturer($request->input('manufacturer_id'));
        }

        if ($request->filled('depreciation_id')) {
            $assets->ByDepreciationId($request->input('depreciation_id'));
        }

        $request->filled('order_number') ? $assets = $assets->where('assets.order_number', '=', e($request->get('order_number'))) : '';

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($assets) && ($request->get('offset') > $assets->count())) ? $assets->count() : $request->get('offset', 0);


        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit'))) ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        // This is used by the audit reporting routes
        if (Gate::allows('audit', Asset::class)) {
            switch ($audit) {
                case 'due':
                    $assets->DueOrOverdueForAudit($settings);
                    break;
                case 'overdue':
                    $assets->overdueForAudit($settings);
                    break;
            }
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $assets->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $assets->TextSearch($request->input('search'));
        }


        // This is kinda gross, but we need to do this because the Bootstrap Tables
        // API passes custom field ordering as custom_fields.fieldname, and we have to strip
        // that out to let the default sorter below order them correctly on the assets table.
        $sort_override = str_replace('custom_fields.', '', $request->input('sort'));

        // This handles all of the pivot sorting (versus the assets.* fields
        // in the allowed_columns array)
        $column_sort = in_array($sort_override, $allowed_columns) ? $sort_override : 'assets.created_at';


        switch ($sort_override) {
            case 'model':
                $assets->OrderModels($order);
                break;
            case 'model_number':
                $assets->OrderModelNumber($order);
                break;
            case 'category':
                $assets->OrderCategory($order);
                break;
            case 'manufacturer':
                $assets->OrderManufacturer($order);
                break;
            case 'company':
                $assets->OrderCompany($order);
                break;
            case 'location':
                $assets->OrderLocation($order);
            case 'rtd_location':
                $assets->OrderRtdLocation($order);
                break;
            case 'status_label':
                $assets->OrderStatus($order);
                break;
            case 'supplier':
                $assets->OrderSupplier($order);
                break;
            case 'assigned_to':
                $assets->OrderAssigned($order);
                break;
            default:
                $assets->orderBy($column_sort, $order);
                break;
        }

        if ($request->notRequest == 1) {
            $assets = $assets->with('finfast_request_asset')->doesntHave('finfast_request_asset');
        }

        if (isset($request->from)) {
            $from = Carbon::createFromFormat('Y-m-d', $request->from)->startOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '>=', $from);
        }
        if (isset($request->to)) {
            $to = Carbon::createFromFormat('Y-m-d', $request->to)->endOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '<=', $to);
        }

        $total = $assets->count();

        $assets = $assets->skip($offset)->take($limit)->get();


        /**
         * Include additional associated relationships
         */
        if ($request->input('components')) {
            $assets->loadMissing(['components' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }]);
        }


        /**
         * Here we're just determining which Transformer (via $transformer) to use based on the 
         * variables we set earlier on in this method - we default to AssetsTransformer.
         */
        return (new $transformer)->transformAssets($assets, $total, $request);
    }

    public function getTotalDetail(Request $request)
    {
        $filter_non_deprecable_assets = false;
        $this->authorize('index', Asset::class);

        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        $assets = Company::scopeCompanyables(Asset::select('assets.*'), 'company_id', 'assets')
            ->with('model.category');
        
        $assets->filterAssetByRole($request->user());

        if ($filter_non_deprecable_assets) {
            $non_deprecable_models = AssetModel::select('id')->whereNotNull('depreciation_id')->get();

            $assets->InModelList($non_deprecable_models->toArray());
        }

        if ($request->filled('assigned_status')) {
            $assets->InAssignedStatus($request->input('assigned_status'));
        }

        if ($request->filled('WAITING_CHECKOUT') || $request->filled('WAITING_CHECKIN')) {
            $assets->where(function ($query) use ($request) {
                $query->where('assets.assigned_status', '=', $request->input('WAITING_CHECKOUT'))
                    ->orWhere('assets.assigned_status', '=', $request->input('WAITING_CHECKIN'));
            });
        }

        if ($request->filled('status_id')) {
            $assets->where('assets.status_id', '=', $request->input('status_id'));
        }

        if ($request->input('requestable') == 'true') {
            $assets->where('assets.requestable', '=', '1');
        }

        if ($request->filled('model_id')) {
            $assets->InModelList([$request->input('model_id')]);
        }

        if ($request->filled('category_id')) {
            $assets->InCategory($request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            $assets->where('assets.location_id', '=', $request->input('location_id'));
        }

        if ($request->filled('dateFrom', 'dateTo')) {
            $assets
                ->whereBetween('assets.purchase_date', [$request->input('dateFrom'), $request->input('dateTo')]);
        }

        if ($request->filled('dateCheckoutFrom', 'dateCheckoutTo')) {
            $filterByCheckoutDate = DateFormatter::formatDate($request->input('dateCheckoutFrom'), $request->input('dateCheckoutTo'));
            $assets
                ->whereBetween('assets.last_checkout', [$filterByCheckoutDate]);
        }

        if ($request->filled('rtd_location_id')) {
            $assets->where('assets.rtd_location_id', '=', $request->input('rtd_location_id'));
        }

        if ($request->filled('supplier_id')) {
            $assets->where('assets.supplier_id', '=', $request->input('supplier_id'));
        }

        if (($request->filled('assigned_to')) && ($request->filled('assigned_type'))) {
            $assets->where('assets.assigned_to', '=', $request->input('assigned_to'))
                ->where('assets.assigned_type', '=', $request->input('assigned_type'));
        }

        if ($request->filled('company_id')) {
            $assets->where('assets.company_id', '=', $request->input('company_id'));
        }

        if ($request->category) {
            $assets->InCategory($request->input('category'));
        }

        if ($request->status_label) {
            $assets->InStatus($request->input('status_label'));
        }

        if ($request->filled('manufacturer_id')) {
            $assets->ByManufacturer($request->input('manufacturer_id'));
        }

        if ($request->filled('depreciation_id')) {
            $assets->ByDepreciationId($request->input('depreciation_id'));
        }

        $request->filled('order_number') ? $assets = $assets->where('assets.order_number', '=', e($request->get('order_number'))) : '';

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $assets->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $assets->TextSearch($request->input('search'));
        }

        if ($request->notRequest == 1) {
            $assets = $assets->with('finfast_request_asset')->doesntHave('finfast_request_asset');
        }

        if (isset($request->from)) {
            $from = Carbon::createFromFormat('Y-m-d', $request->from)->startOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '>=', $from);
        }
        if (isset($request->to)) {
            $to = Carbon::createFromFormat('Y-m-d', $request->to)->endOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '<=', $to);
        }

        $total_asset_by_model = $assets->selectRaw('model_id , count(*) as total')->groupBy('model_id')->pluck('total','model_id');
        $total_asset_by_model->transform(function ($value,$key) {
            return [
                'name' => AssetModel::findOrFail($key)->category()->pluck('name')[0],
                'total' => $value
            ];
        });
        $total_detail = $total_asset_by_model->groupBy('name')->map(function ($item) {
            return [
                'name' => $item->first()['name'],
                'total' => $item->sum('total')
            ];
        })->values()->toArray();

        return response()->json(Helper::formatStandardApiResponse('success', $total_detail, trans('admin/hardware/message.update.success')));
    }

    public function assetExpiration(Request $request, $audit = null)
    {
        \Log::debug(Route::currentRouteName());
        $filter_non_deprecable_assets = false;

        if (Route::currentRouteName() == 'api.depreciation-report.index') {
            $filter_non_deprecable_assets = true;
            $transformer = 'App\Http\Transformers\DepreciationReportTransformer';
            $this->authorize('reports.view');
        } else {
            $transformer = 'App\Http\Transformers\AssetsTransformer';
            $this->authorize('index', Asset::class);
        }


        $settings = Setting::getSettings();

        $allowed_columns = [
            'id',
            'name',
            'asset_tag',
            'serial',
            'model_number',
            'last_checkout',
            'notes',
            'expected_checkin',
            'order_number',
            'image',
            'assigned_to',
            'created_at',
            'updated_at',
            'purchase_date',
            'purchase_cost',
            'last_audit_date',
            'next_audit_date',
            'assigned_status',
            'requestable',
            'warranty_months',
            'checkout_counter',
            'checkin_counter',
            'requests_counter',
        ];

        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        $all_custom_fields = CustomField::all(); //used as a 'cache' of custom fields throughout this page load
        foreach ($all_custom_fields as $field) {
            $allowed_columns[] = $field->db_column_name();
        }

        $assets = Company::scopeCompanyables(Asset::select('assets.*'), 'company_id', 'assets')
            ->with(
                'location',
                'assetstatus',
                'company',
                'defaultLoc',
                'assignedTo',
                'model.category',
                'model.manufacturer',
                'model.fieldset',
                'supplier'
            ); //it might be tempting to add 'assetlog' here, but don't. It blows up update-heavy users.

        $assets->filterAssetByRole($request->user());
        if ($filter_non_deprecable_assets) {
            $non_deprecable_models = AssetModel::select('id')->whereNotNull('depreciation_id')->get();

            $assets->InModelList($non_deprecable_models->toArray());
        }

        // Search custom fields by column name
        foreach ($all_custom_fields as $field) {
            if ($request->filled($field->db_column_name())) {
                $assets->where($field->db_column_name(), '=', $request->input($field->db_column_name()));
            }
        }

        if ($request->filled('assigned_status')) {
            $assets->where('assets.assigned_status', '=', $request->input('assigned_status'));
        }

        if ($request->filled('WAITING_CHECKOUT') || $request->filled('WAITING_CHECKIN')) {
            $assets->where(function ($query) use ($request) {
                $query->where('assets.assigned_status', '=', $request->input('WAITING_CHECKOUT'))
                    ->orWhere('assets.assigned_status', '=', $request->input('WAITING_CHECKIN'));
            });
        }

        if ($request->filled('status_id')) {
            $assets->where('assets.status_id', '=', $request->input('status_id'));
        }

        if ($request->input('requestable') == 'true') {
            $assets->where('assets.requestable', '=', '1');
        }

        if ($request->filled('model_id')) {
            $assets->InModelList([$request->input('model_id')]);
        }

        if ($request->filled('category_id')) {
            $assets->InCategory($request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            $assets->where('assets.location_id', '=', $request->input('location_id'));
        }

        if ($request->filled('dateFrom', 'dateTo')) {
            $assets
                ->whereBetween('assets.purchase_date', [$request->input('dateFrom'), $request->input('dateTo')]);
        }

        if ($request->filled('rtd_location_id')) {
            $assets->where('assets.rtd_location_id', '=', $request->input('rtd_location_id'));
        }

        if ($request->filled('supplier_id')) {
            $assets->where('assets.supplier_id', '=', $request->input('supplier_id'));
        }

        if (($request->filled('assigned_to')) && ($request->filled('assigned_type'))) {
            $assets->where('assets.assigned_to', '=', $request->input('assigned_to'))
                ->where('assets.assigned_type', '=', $request->input('assigned_type'));
        }

        if ($request->filled('company_id')) {
            $assets->where('assets.company_id', '=', $request->input('company_id'));
        }

        if ($request->category) {
            $assets->InCategory($request->input('category'));
        }

        if ($request->status_label) {
            $assets->where('assets.status_id', '=', $request->input('status_label'));
        }

        if ($request->filled('manufacturer_id')) {
            $assets->ByManufacturer($request->input('manufacturer_id'));
        }

        if ($request->filled('depreciation_id')) {
            $assets->ByDepreciationId($request->input('depreciation_id'));
        }

        $request->filled('order_number') ? $assets = $assets->where('assets.order_number', '=', e($request->get('order_number'))) : '';

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($assets) && ($request->get('offset') > $assets->count())) ? $assets->count() : $request->get('offset', 0);


        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit'))) ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        // This is used by the audit reporting routes
        if (Gate::allows('audit', Asset::class)) {
            switch ($audit) {
                case 'due':
                    $assets->DueOrOverdueForAudit($settings);
                    break;
                case 'overdue':
                    $assets->overdueForAudit($settings);
                    break;
            }
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $assets->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $assets->TextSearch($request->input('search'));
        }


        // This is kinda gross, but we need to do this because the Bootstrap Tables
        // API passes custom field ordering as custom_fields.fieldname, and we have to strip
        // that out to let the default sorter below order them correctly on the assets table.
        $sort_override = str_replace('custom_fields.', '', $request->input('sort'));

        // This handles all of the pivot sorting (versus the assets.* fields
        // in the allowed_columns array)
        $column_sort = in_array($sort_override, $allowed_columns) ? $sort_override : 'assets.created_at';


        switch ($sort_override) {
            case 'model':
                $assets->OrderModels($order);
                break;
            case 'model_number':
                $assets->OrderModelNumber($order);
                break;
            case 'category':
                $assets->OrderCategory($order);
                break;
            case 'manufacturer':
                $assets->OrderManufacturer($order);
                break;
            case 'company':
                $assets->OrderCompany($order);
                break;
            case 'location':
                $assets->OrderLocation($order);
            case 'rtd_location':
                $assets->OrderRtdLocation($order);
                break;
            case 'status_label':
                $assets->OrderStatus($order);
                break;
            case 'supplier':
                $assets->OrderSupplier($order);
                break;
            case 'assigned_to':
                $assets->OrderAssigned($order);
                break;
            default:
                $assets->orderBy($column_sort, $order);
                break;
        }

        if ($request->notRequest == 1) {
            $assets = $assets->with('finfast_request_asset')->doesntHave('finfast_request_asset');
        }

        if (isset($request->from)) {
            $from = Carbon::createFromFormat('Y-m-d', $request->from)->startOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '>=', $from);
        }
        if (isset($request->to)) {
            $to = Carbon::createFromFormat('Y-m-d', $request->to)->endOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '<=', $to);
        }

        $total = $assets->count();

        $assets = $assets->skip($offset)->take($limit)->get();


        /**
         * Include additional associated relationships
         */
        if ($request->input('components')) {
            $assets->loadMissing(['components' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }]);
        }


        /**
         * Here we're just determining which Transformer (via $transformer) to use based on the 
         * variables we set earlier on in this method - we default to AssetsTransformer.
         */
        $expiration = Carbon::now()->addDays(30)->startOfDay()->toDateTimeString();

        $data = [];
        $data['total'] = 0;
        $assets =  (new $transformer)->transformAssets($assets, $total, $request);
        foreach ($assets['rows'] as $asset) {
            if (!$asset['warranty_expires']) continue;
            if ((new Carbon($asset['warranty_expires']['date']))->lte($expiration)) {
                $data['rows'][] = $asset;
                $data['total'] += 1;
            }
        }
        return $data;
    }
    /**
     * Returns JSON with information about an asset (by tag) for detail view.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param string $tag
     * @since [v4.2.1]
     * @return JsonResponse
     */
    public function showByTag(Request $request, $tag)
    {
        if ($asset = Asset::with('assetstatus')->with('assignedTo')->where('asset_tag', $tag)->first()) {
            $this->authorize('view', $asset);

            return (new AssetsTransformer)->transformAsset($asset, $request);
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, 'Asset not found'), 200);
    }

    /**
     * Returns JSON with information about an asset (by serial) for detail view.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param string $serial
     * @since [v4.2.1]
     * @return JsonResponse
     */
    public function showBySerial(Request $request, $serial)
    {
        $this->authorize('index', Asset::class);
        if ($assets = Asset::with('assetstatus')->with('assignedTo')
            ->withTrashed()->where('serial', $serial)->get()
        ) {
            return (new AssetsTransformer)->transformAssets($assets, $assets->count());
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, 'Asset not found'), 200);

        $assets = Asset::with('assetstatus')->with('assignedTo');

        if ($request->input('deleted', 'false') === 'true') {
            $assets = $assets->withTrashed();
        }

        $assets = $assets->where('serial', $serial)->get();
        if ($assets) {
            return (new AssetsTransformer)->transformAssets($assets, $assets->count());
        } else {
            return response()->json(Helper::formatStandardApiResponse('error', null, 'Asset not found'), 200);
        }
    }

    /**
     * Returns JSON with information about an asset for detail view.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param int $assetId
     * @since [v4.0]
     * @return JsonResponse
     */
    public function show(Request $request, $id)
    {
        if ($asset = Asset::with('assetstatus')->with('assignedTo')->withTrashed()
            ->withCount('checkins as checkins_count', 'checkouts as checkouts_count', 'userRequests as user_requests_count')->findOrFail($id)
        ) {
            $this->authorize('view', $asset);

            return (new AssetsTransformer)->transformAsset($asset, $request->input('components'));
        }
    }


    public function assign(Request $request, $audit = null)
    {
        $user_id = Auth::id();
        \Log::debug(Route::currentRouteName());
        $filter_non_deprecable_assets = false;

        /**
         * This looks MAD janky (and it is), but the AssetsController@index does a LOT of heavy lifting throughout the 
         * app. This bit here just makes sure that someone without permission to view assets doesn't 
         * end up with priv escalations because they asked for a different endpoint. 
         * 
         * Since we never gave the specification for which transformer to use before, it should default 
         * gracefully to just use the AssetTransformer by default, which shouldn't break anything. 
         * 
         * It was either this mess, or repeating ALL of the searching and sorting and filtering code, 
         * which would have been far worse of a mess. *sad face*  - snipe (Sept 1, 2021)
         */
        if (Route::currentRouteName() == 'api.depreciation-report.index') {
            $filter_non_deprecable_assets = true;
            $transformer = 'App\Http\Transformers\DepreciationReportTransformer';
            $this->authorize('reports.view');
        } else {
            $transformer = 'App\Http\Transformers\AssetsTransformer';
            $this->authorize('index', Asset::class);
        }


        $settings = Setting::getSettings();

        $allowed_columns = [
            'id',
            'name',
            'asset_tag',
            'serial',
            'model_number',
            'last_checkout',
            'notes',
            'expected_checkin',
            'order_number',
            'image',
            'assigned_to',
            'created_at',
            'updated_at',
            'purchase_date',
            'purchase_cost',
            'last_audit_date',
            'next_audit_date',
            'assigned_status',
            'requestable',
            'warranty_months',
            'checkout_counter',
            'checkin_counter',
            'requests_counter',
        ];

        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        $all_custom_fields = CustomField::all(); //used as a 'cache' of custom fields throughout this page load
        foreach ($all_custom_fields as $field) {
            $allowed_columns[] = $field->db_column_name();
        }

        $assets = Company::scopeCompanyables(Asset::select('assets.*'), 'company_id', 'assets')
            ->with(
                'location',
                'assetstatus',
                'company',
                'defaultLoc',
                'assignedTo',
                'model.category',
                'model.manufacturer',
                'model.fieldset',
                'supplier'
            ); //it might be tempting to add 'assetlog' here, but don't. It blows up update-heavy users.

        if ($filter_non_deprecable_assets) {
            $non_deprecable_models = AssetModel::select('id')->whereNotNull('depreciation_id')->get();

            $assets->InModelList($non_deprecable_models->toArray());
        }

        // These are used by the API to query against specific ID numbers.
        // They are also used by the individual searches on detail pages like
        // locations, etc.


        // Search custom fields by column name
        foreach ($all_custom_fields as $field) {
            if ($request->filled($field->db_column_name())) {
                $assets->where($field->db_column_name(), '=', $request->input($field->db_column_name()));
            }
        }

        if ($request->filled('status_id')) {
            $assets->where('assets.status_id', '=', $request->input('status_id'));
        }

        if ($request->filled('assigned_status')) {
            $assets->InAssignedStatus($request->input('assigned_status'));
        }

        if ($request->input('requestable') == 'true') {
            $assets->where('assets.requestable', '=', '1');
        }

        if ($request->filled('model_id')) {
            $assets->InModelList([$request->input('model_id')]);
        }

        if ($request->filled('category_id')) {
            $assets->InCategory($request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            $assets->where('assets.location_id', '=', $request->input('location_id'));
        }

        if ($request->filled('rtd_location_id')) {
            $assets->where('assets.rtd_location_id', '=', $request->input('rtd_location_id'));
        }

        if ($request->filled('supplier_id')) {
            $assets->where('assets.supplier_id', '=', $request->input('supplier_id'));
        }

        if (($request->filled('assigned_to')) && ($request->filled('assigned_type'))) {
            $assets->where('assets.assigned_to', '=', $request->input('assigned_to'))
                ->where('assets.assigned_type', '=', $request->input('assigned_type'));
        }

        if ($request->filled('company_id')) {
            $assets->where('assets.company_id', '=', $request->input('company_id'));
        }

        if ($request->filled('manufacturer_id')) {
            $assets->ByManufacturer($request->input('manufacturer_id'));
        }

        if ($request->filled('depreciation_id')) {
            $assets->ByDepreciationId($request->input('depreciation_id'));
        }

        $request->filled('order_number') ? $assets = $assets->where('assets.order_number', '=', e($request->get('order_number'))) : '';

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($assets) && ($request->get('offset') > $assets->count())) ? $assets->count() : $request->get('offset', 0);


        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit'))) ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        // This is used by the audit reporting routes
        if (Gate::allows('audit', Asset::class)) {
            switch ($audit) {
                case 'due':
                    $assets->DueOrOverdueForAudit($settings);
                    break;
                case 'overdue':
                    $assets->overdueForAudit($settings);
                    break;
            }
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $assets->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $assets->TextSearch($request->input('search'));
        }


        // This is kinda gross, but we need to do this because the Bootstrap Tables
        // API passes custom field ordering as custom_fields.fieldname, and we have to strip
        // that out to let the default sorter below order them correctly on the assets table.
        $sort_override = str_replace('custom_fields.', '', $request->input('sort'));

        // This handles all of the pivot sorting (versus the assets.* fields
        // in the allowed_columns array)
        $column_sort = in_array($sort_override, $allowed_columns) ? $sort_override : 'created_at';


        switch ($sort_override) {
            case 'model':
                $assets->OrderModels($order);
                break;
            case 'model_number':
                $assets->OrderModelNumber($order);
                break;
            case 'category':
                $assets->OrderCategory($order);
                break;
            case 'manufacturer':
                $assets->OrderManufacturer($order);
                break;
            case 'company':
                $assets->OrderCompany($order);
                break;
            case 'location':
                $assets->OrderLocation($order);
            case 'rtd_location':
                $assets->OrderRtdLocation($order);
                break;
            case 'status_label':
                $assets->OrderStatus($order);
                break;
            case 'supplier':
                $assets->OrderSupplier($order);
                break;
            case 'assigned_to':
                $assets->OrderAssigned($order);
                break;
            case 'assigned_status':
                $assets->OrderAssignedStatus($order);
                break;
            case 'updated_at':
                $assets->OrderUpdatedAt($order);
                break;
            default:
                $priority_assign_status = config('enum.assigned_status.WAITING');
                $assets->orderByRaw(
                    "CASE WHEN assets.assigned_status = $priority_assign_status THEN 1 ELSE 0 END DESC"
                );
                $assets->orderBy($column_sort, $order);
                break;
        }

        if ($request->notRequest == 1) {
            $assets = $assets->with('finfast_request_asset')->doesntHave('finfast_request_asset');
        }

        if (isset($request->from)) {
            $from = Carbon::createFromFormat('Y-m-d', $request->from)->startOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '>=', $from);
        }
        if (isset($request->to)) {
            $to = Carbon::createFromFormat('Y-m-d', $request->to)->endOfDay()->toDateTimeString();
            $assets = $assets->where('created_at', '<=', $to);
        }

        $assets = $assets->where('assets.assigned_to', '=', $user_id)->skip($offset)->take($limit)->get();
        $total = $assets->count();

        /**
         * Include additional associated relationships
         */
        if ($request->input('components')) {
            $assets->loadMissing(['components' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }]);
        }

        /**
         * Here we're just determining which Transformer (via $transformer) to use based on the 
         * variables we set earlier on in this method - we default to AssetsTransformer.
         */

        return (new $transformer)->transformAssets($assets, $total, $request);
    }

    public function licenses(Request $request, $id)
    {
        $this->authorize('view', Asset::class);
        $this->authorize('view', License::class);
        $asset = Asset::where('id', $id)->withTrashed()->first();
        $licenses = $asset->licenses()->get();

        return (new LicensesTransformer())->transformLicenses($licenses, $licenses->count());
    }


    /**
     * Gets a paginated collection for the select2 menus
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0.16]
     * @see \App\Http\Transformers\SelectlistTransformer
     *
     */
    public function selectlist(Request $request)
    {

        $assets = Company::scopeCompanyables(Asset::select([
            'assets.id',
            'assets.name',
            'assets.asset_tag',
            'assets.model_id',
            'assets.assigned_to',
            'assets.assigned_type',
            'assets.status_id',
        ])->with('model', 'assetstatus', 'assignedTo')->NotArchived(), 'company_id', 'assets');

        if ($request->filled('assetStatusType') && $request->input('assetStatusType') === 'RTD') {
            $assets = $assets->RTD();
        }

        if ($request->filled('search')) {
            $assets = $assets->AssignedSearch($request->input('search'));
        }


        $assets = $assets->paginate(50);

        // Loop through and set some custom properties for the transformer to use.
        // This lets us have more flexibility in special cases like assets, where
        // they may not have a ->name value but we want to display something anyway
        foreach ($assets as $asset) {


            $asset->use_text = $asset->present()->fullName;

            if (($asset->checkedOutToUser()) && ($asset->assigned)) {
                $asset->use_text .= ' → ' . $asset->assigned->getFullNameAttribute();
            }


            if ($asset->assetstatus->getStatuslabelType() == 'pending') {
                $asset->use_text .= '(' . $asset->assetstatus->getStatuslabelType() . ')';
            }

            $asset->use_image = ($asset->image_url) ? $asset->image_url : null;
        }

        return (new SelectlistTransformer)->transformSelectlist($assets);
    }


    /**
     * Accepts a POST request to create a new asset
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param \App\Http\Requests\ImageUploadRequest $request
     * @since [v4.0]
     * @return JsonResponse
     */
    public function store(ImageUploadRequest $request)
    {
        $this->authorize('create', Asset::class);

        $asset = new Asset();
        $asset->model()->associate(AssetModel::find((int) $request->get('model_id')));

        $asset->name                    = $request->get('name');
        $asset->serial                  = $request->get('serial');
        $asset->company_id              = Company::getIdForCurrentUser($request->get('company_id'));
        $asset->model_id                = $request->get('model_id');
        $asset->order_number            = $request->get('order_number');
        $asset->notes                   = $request->get('notes');
        $asset->asset_tag               = $request->get('asset_tag', Asset::autoincrement_asset());
        $asset->user_id                 = Auth::id();
        $asset->archived                = '0';
        $asset->physical                = '1';
        $asset->depreciate              = '0';
        $asset->status_id               = $request->get('status_id', 0);
        $asset->warranty_months         = $request->get('warranty_months', null);
        $asset->purchase_cost           = Helper::ParseCurrency($request->get('purchase_cost')); // this is the API's store method, so I don't know that I want to do this? Confusing. FIXME (or not?!)
        $asset->purchase_date           = $request->get('purchase_date', null);
        $asset->assigned_to             = $request->get('assigned_to', null);
        $asset->supplier_id             = $request->get('supplier_id', 0);
        $asset->requestable             = $request->get('requestable', 0);
        $asset->rtd_location_id         = $request->get('rtd_location_id', null);
        $asset->location_id             = $request->get('location_id', null);
        $asset->assigned_status         = $request->get('assigned_status', 0);

        /**
         * this is here just legacy reasons. Api\AssetController
         * used image_source  once to allow encoded image uploads.
         */
        if ($request->has('image_source')) {
            $request->offsetSet('image', $request->offsetGet('image_source'));
        }

        $asset = $request->handleImages($asset);
        // Update custom fields in the database.
        // Validation for these fields is handled through the AssetRequest form request
        $model = AssetModel::find($request->get('model_id'));
        if (($model) && ($model->fieldset)) {
            foreach ($model->fieldset->fields as $field) {

                // Set the field value based on what was sent in the request
                $field_val = $request->input($field->convertUnicodeDbSlug(), null);

                // If input value is null, use custom field's default value
                if ($field_val == null) {
                    \Log::debug('Field value for ' . $field->convertUnicodeDbSlug() . ' is null');
                    $field_val = $field->defaultValue($request->get('model_id'));
                    \Log::debug('Use the default fieldset value of ' . $field->defaultValue($request->get('model_id')));
                }

                // if the field is set to encrypted, make sure we encrypt the value
                if ($field->field_encrypted == '1') {
                    \Log::debug('This model field is encrypted in this fieldset.');

                    if (Gate::allows('admin')) {

                        // If input value is null, use custom field's default value
                        if (($field_val == null) && ($request->has('model_id') != '')) {
                            $field_val = \Crypt::encrypt($field->defaultValue($request->get('model_id')));
                        } else {
                            $field_val = \Crypt::encrypt($request->input($field->convertUnicodeDbSlug()));
                        }
                    }
                }


                $asset->{$field->convertUnicodeDbSlug()} = $field_val;
            }
        }

        if ($asset->save()) {
            if ($asset->image) {
                $asset->image = $asset->image_url;
            }

            return response()->json(Helper::formatStandardApiResponse('success', $asset, trans('admin/hardware/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $asset->getErrors()),  Response::HTTP_UNPROCESSABLE_ENTITY);
    }


    /**
     * Accepts a POST request to update an asset
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param \App\Http\Requests\ImageUploadRequest $request
     * @since [v4.0]
     * @return JsonResponse
     */
    public function update(ImageUploadRequest $request, $id)
    {
        $this->authorize('update', Asset::class);

        if ($asset = Asset::find($id)) {
            $asset->fill($request->all());
            $assigned_status = $asset->assigned_status;
            ($request->filled('model_id')) ?
                $asset->model()->associate(AssetModel::find($request->get('model_id'))) : null;
            ($request->filled('rtd_location_id')) ?
                $asset->location_id = $request->get('rtd_location_id') : '';
            ($request->filled('company_id')) ?
                $asset->company_id = Company::getIdForCurrentUser($request->get('company_id')) : '';
            ($request->filled('rtd_location_id')) ?
                $asset->location_id = $request->get('rtd_location_id') : null;
            /**
             * this is here just legacy reasons. Api\AssetController
             * used image_source  once to allow encoded image uploads.
             */
            if ($request->has('image_source')) {
                $request->offsetSet('image', $request->offsetGet('image_source'));
            }
            $asset = $request->handleImages($asset);

            // Update custom fields
            if (($model = AssetModel::find($asset->model_id)) && (isset($model->fieldset))) {
                foreach ($model->fieldset->fields as $field) {
                    if ($request->has($field->convertUnicodeDbSlug())) {
                        if ($field->field_encrypted == '1') {
                            if (Gate::allows('admin')) {
                                $asset->{$field->convertUnicodeDbSlug()} = \Crypt::encrypt($request->input($field->convertUnicodeDbSlug()));
                            }
                        } else {
                            $asset->{$field->convertUnicodeDbSlug()} = $request->input($field->convertUnicodeDbSlug());
                        }
                    }
                }
            }
            $user = null;
            if ($asset->assigned_to) {
                $user = User::find($asset->assigned_to);
            }
            if ($user && $request->has('assigned_status') && $assigned_status !== $request->get('assigned_status')) {
                $asset->assigned_status = $request->get('assigned_status');
                $it_ncc_email = Setting::first()->admin_cc_email;
                $user_name = $user->first_name . ' ' . $user->last_name;
                $current_time = Carbon::now();
                $data = [
                    'user_name' => $user_name,
                    'is_confirm' => '',
                    'asset_name' => $asset->name,
                    'time' => $current_time->format('d-m-Y'),
                    'reason' => '',
                ];
                if ($asset->assigned_status === config('enum.assigned_status.ACCEPT')) {
                    $data['is_confirm'] = 'đã xác nhận';
                    $data['asset_count'] = 1;
                    if ($asset->withdraw_from) {
                        $asset->increment('checkin_counter', 1);
                        $data['is_confirm'] = 'đã xác nhận thu hồi';
                        if ($asset->status_id != config('enum.status_id.PENDING') && $asset->status_id != config('enum.status_id.BROKEN')) {
                            $asset->status_id = config('enum.status_id.READY_TO_DEPLOY');
                        }
                        $asset->assigned_status = config('enum.assigned_status.DEFAULT');
                        $asset->withdraw_from = null;
                        $asset->expected_checkin = null;
                        $asset->last_checkout = null;
                        $asset->assigned_to = null;
                        $asset->assignedTo()->disassociate($this);
                        $asset->accepted = null;
                        SendConfirmRevokeMail::dispatch($data, $it_ncc_email);
                    } else {
                        $asset->increment('checkout_counter', 1);
                        $data['is_confirm'] = 'đã xác nhận cấp phát';
                        $asset->status_id = config('enum.status_id.ASSIGN');
                        SendConfirmMail::dispatch($data, $it_ncc_email);
                    }
                } elseif ($asset->assigned_status === config('enum.assigned_status.REJECT')) {
                    $data['asset_count'] = 1;
                    if ($asset->withdraw_from) {
                        $data['is_confirm'] = 'đã từ chối thu hồi';
                        $asset->status_id = config('enum.status_id.ASSIGN');
                        $asset->assigned_status = config('enum.assigned_status.ACCEPT');
                        $data['reason'] = 'Lý do: ' . $request->get('reason');
                        SendRejectRevokeMail::dispatch($data, $it_ncc_email);
                    } else {
                        $data['is_confirm'] = 'đã từ chối nhận';
                        $asset->status_id = config('enum.status_id.READY_TO_DEPLOY');
                        $asset->assigned_status = config('enum.assigned_status.DEFAULT');
                        $data['reason'] = 'Lý do: ' . $request->get('reason');
                        $asset->withdraw_from = null;
                        $asset->expected_checkin = null;
                        $asset->last_checkout = null;
                        $asset->assigned_to = null;
                        $asset->assignedTo()->disassociate($this);
                        $asset->accepted = null;
                        SendRejectAllocateMail::dispatch($data, $it_ncc_email);
                    }
                }
            }

            if ($asset->save()) {
                if ($asset->image) {
                    $asset->image = $asset->image_url;
                }

                return response()->json(Helper::formatStandardApiResponse('success', $asset, trans('admin/hardware/message.update.success')));
            }

            return response()->json(Helper::formatStandardApiResponse('error', null, $asset->getErrors()), 400);
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/hardware/message.does_not_exist')), 200);
    }

    public function multiUpdate(ImageUploadRequest $request)
    {
        $this->authorize('update', Asset::class);
        $asset_ids = $request->assets;
        $asset_names = null;
        $assets = array();
        foreach ($asset_ids as $id) {
            if ($asset = Asset::find($id)) {
                $asset->fill($request->all());
                $assigned_status = $asset->assigned_status;
                ($request->filled('model_id')) ?
                    $asset->model()->associate(AssetModel::find($request->get('model_id'))) : null;
                ($request->filled('assigned_status')) ?
                    $asset->assigned_status = $request->get('assigned_status') : '';
                ($request->filled('rtd_location_id')) ?
                    $asset->location_id = $request->get('rtd_location_id') : '';
                ($request->filled('company_id')) ?
                    $asset->company_id = Company::getIdForCurrentUser($request->get('company_id')) : '';
                ($request->filled('rtd_location_id')) ?
                    $asset->location_id = $request->get('rtd_location_id') : null;

                /**
                 * this is here just legacy reasons. Api\AssetController
                 * used image_source  once to allow encoded image uploads.
                 */
                if ($request->has('image_source')) {
                    $request->offsetSet('image', $request->offsetGet('image_source'));
                }

                $asset = $request->handleImages($asset);

                // Update custom fields
                if (($model = AssetModel::find($asset->model_id)) && (isset($model->fieldset))) {
                    foreach ($model->fieldset->fields as $field) {
                        if ($request->has($field->convertUnicodeDbSlug())) {
                            if ($field->field_encrypted == '1') {
                                if (Gate::allows('admin')) {
                                    $asset->{$field->convertUnicodeDbSlug()} = \Crypt::encrypt($request->input($field->convertUnicodeDbSlug()));
                                }
                            } else {
                                $asset->{$field->convertUnicodeDbSlug()} = $request->input($field->convertUnicodeDbSlug());
                            }
                        }
                    }
                }
                $user = null;
                if ($asset->assigned_to) {
                    $user = User::find($asset->assigned_to);
                }

                if ($id === end($asset_ids)) {
                    $asset_names .= $asset->name;
                } else {
                    $asset_names .= $asset->name . ", ";
                }

                if ($user && $request->has('assigned_status') && $assigned_status !== $request->get('assigned_status')) {
                    $it_ncc_email = Setting::first()->admin_cc_email;
                    $user_name = $user->first_name . ' ' . $user->last_name;
                    $current_time = Carbon::now();
                    $data = [
                        'user_name' => $user_name,
                        'is_confirm' => '',
                        'asset_name' => $asset_names,
                        'time' => $current_time->format('d-m-Y'),
                        'reason' => '',
                        'asset_count' => count($asset_ids)

                    ];
                    if ($asset->assigned_status === config('enum.assigned_status.ACCEPT')) {

                        // $data['asset_count'] = 1;
                        if ($asset->withdraw_from) {
                            $data['is_confirm'] = 'đã xác nhận thu hồi';
                            if ($asset->status_id != config('enum.status_id.PENDING') && $asset->status_id != config('enum.status_id.BROKEN')) {
                                $asset->status_id = config('enum.status_id.READY_TO_DEPLOY');
                            }
                            $asset->assigned_status = config('enum.assigned_status.DEFAULT');
                            $asset->withdraw_from = null;
                            $asset->expected_checkin = null;
                            $asset->last_checkout = null;
                            $asset->assigned_to = null;
                            $asset->assignedTo()->disassociate($this);
                            $asset->accepted = null;

                            if ($id === end($asset_ids)) {
                                SendConfirmRevokeMail::dispatch($data, $it_ncc_email);
                            }
                        } else {
                            $data['is_confirm'] = 'đã xác nhận cấp phát';
                            $asset->status_id = config('enum.status_id.ASSIGN');

                            if ($id === end($asset_ids)) {
                                SendConfirmMail::dispatch($data, $it_ncc_email);
                            }
                        }
                    } elseif ($asset->assigned_status === config('enum.assigned_status.REJECT')) {
                        if ($asset->withdraw_from) {
                            $data['is_confirm'] = 'đã từ chối thu hồi';
                            $asset->withdraw_from = null;
                            $asset->status_id = config('enum.status_id.ASSIGN');
                            $asset->assigned_status = config('enum.assigned_status.ACCEPT');
                            $data['reason'] = 'Lý do: ' . $request->get('reason');

                            if ($id === end($asset_ids)) {
                                SendRejectRevokeMail::dispatch($data, $it_ncc_email);
                            }
                        } else {
                            $data['is_confirm'] = 'đã từ chối nhận';
                            $asset->status_id = config('enum.status_id.READY_TO_DEPLOY');
                            $asset->assigned_status = config('enum.assigned_status.DEFAULT');
                            $asset->withdraw_from = null;
                            $asset->expected_checkin = null;
                            $asset->last_checkout = null;
                            $asset->assigned_to = null;
                            $asset->assignedTo()->disassociate($this);
                            $asset->accepted = null;
                            $data['reason'] = 'Lý do: ' . $request->get('reason');

                            if ($id === end($asset_ids)) {
                                SendRejectAllocateMail::dispatch($data, $it_ncc_email);
                            }
                        }
                    }
                }

                if ($asset->save()) {
                    if ($asset->image) {
                        $asset->image = $asset->image_url;
                    }

                    array_push($assets, $asset);
                    if ($id === end($asset_ids)) {
                        return response()->json(Helper::formatStandardApiResponse('success', $assets, trans('admin/hardware/message.update.success')));
                    }
                } else {
                    return response()->json(Helper::formatStandardApiResponse('error', null, $asset->getErrors()), 200);
                }
            } else {
                return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/hardware/message.does_not_exist')), 200);
            }
        }
    }


    /**
     * Delete a given asset (mark as deleted).
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param int $assetId
     * @since [v4.0]
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $this->authorize('delete', Asset::class);

        if ($asset = Asset::find($id)) {
            $this->authorize('delete', $asset);

            DB::table('assets')
                ->where('id', $asset->id)
                ->update(['assigned_to' => null]);

            $asset->delete();

            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/hardware/message.delete.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/hardware/message.does_not_exist')), 200);
    }



    /**
     * Restore a soft-deleted asset.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param int $assetId
     * @since [v5.1.18]
     * @return JsonResponse
     */
    public function restore($assetId = null)
    {
        // Get asset information
        $asset = Asset::withTrashed()->find($assetId);
        $this->authorize('delete', $asset);
        if (isset($asset->id)) {
            // Restore the asset
            Asset::withTrashed()->where('id', $assetId)->restore();

            $logaction = new Actionlog();
            $logaction->item_type = Asset::class;
            $logaction->item_id = $asset->id;
            $logaction->created_at =  date("Y-m-d H:i:s");
            $logaction->user_id = Auth::user()->id;
            $logaction->logaction('restored');

            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/hardware/message.restore.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/hardware/message.does_not_exist')), 200);
    }



    /**
     * Checkout an asset
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param int $assetId
     * @since [v4.0]
     * @return JsonResponse
     */
    public function multiCheckout(AssetCheckoutRequest $request)
    {
        $this->authorize('checkout', Asset::class);

        $assets = request('assets');
        $asset_name = null;
        $asset_tag = null;

        foreach ($assets as $asset_id) {

            $asset_id = $asset_id;
            $asset = Asset::findOrFail($asset_id);

            if (!$asset->availableForCheckout()) {
                return response()->json(Helper::formatStandardApiResponse('error', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkout.not_available')));
            }

            $this->authorize('checkout', $asset);

            $error_payload = [];
            $error_payload['asset'] = [
                'id' => $asset->id,
                'asset_tag' => $asset->asset_tag,
            ];

            if (request('checkout_to_type') == 'user') {
                // Fetch the target and set the asset's new location_id
                $target = User::find(request('assigned_user'));
                // $asset->location_id = (($target) && (isset($target->location_id))) ? $target->location_id : '';
                $error_payload['target_id'] = $request->input('assigned_user');
                $error_payload['target_type'] = 'user';
            }

            if (!isset($target)) {
                return response()->json(Helper::formatStandardApiResponse('error', $error_payload, 'Checkout target for asset ' . e($asset->asset_tag) . ' is invalid - ' . $error_payload['target_type'] . ' does not exist.'));
            }

            $checkout_at = request('checkout_at', date('Y-m-d H:i:s'));
            $expected_checkin = request('expected_checkin', null);
            $note = request('note', null);

            // Set the location ID to the RTD location id if there is one
            // Wait, why are we doing this? This overrides the stuff we set further up, which makes no sense.
            // TODO: Follow up here. WTF. Commented out for now. 

            $user = User::find($request->assigned_user);
            $user_email = $user->email;
            $user_name = $user->first_name . ' ' . $user->last_name;
            $current_time = Carbon::now();
            $location = Location::find($asset->location_id);
            $location_address = null;

            // concat asset's address information
            $location_arr = array();

            if (!is_null($location)) {
                if (!is_null($location->address2)) {
                    array_push($location_arr, $location->address2);
                }

                if (!is_null($location->address)) {
                    array_push($location_arr, $location->address);
                }

                if (!is_null($location->state)) {
                    array_push($location_arr, $location->state);
                }

                if (!is_null($location->city)) {
                    array_push($location_arr, $location->city);
                }
            }

            foreach ($location_arr as $value) {
                if ($value === end($location_arr)) {
                    $location_address .= $value . '.';
                } else {
                    $location_address .= $value . ', ';
                }
            }

            // concat assets' names and assets' tags
            if ($asset_id === end($assets)) {
                $asset_name .= $asset->name;
                $asset_tag .= $asset->asset_tag;
            } else {
                $asset_name .= $asset->name . ", ";
                $asset_tag .= $asset->asset_tag . ", ";
            }

            $asset->status_id = config('enum.status_id.ASSIGN');

            if ($asset->checkOut($target, Auth::user(), $checkout_at, $expected_checkin, $note, $asset->name, $asset->location_id, config('enum.assigned_status.WAITINGCHECKOUT'))) {
                $this->saveAssetHistory($asset_id, config('enum.asset_history.CHECK_OUT_TYPE'));
            }
        }

        $data = [
            'user_name' => $user_name,
            'asset_name' => $asset_name,
            'count' => count($assets),
            'location_address' => $location_address,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];
        SendCheckoutMail::dispatch($data, $user_email);
        return response()->json(Helper::formatStandardApiResponse('success', ['asset' => e($asset_tag)], trans('admin/hardware/message.checkout.success')));
    }


    /**
     * Checkin an asset
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param int $assetId
     * @since [v4.0]
     * @return JsonResponse
     */
    public function multiCheckin(Request $request, $type = null)
    {
        $assets = $request->assets;
        $asset_tag = null;
        $asset = Asset::findOrFail($assets);
        $listAsset = [];
        $item = 0;
        foreach ($asset as $value) {
            $item++;
            $listAsset[$value['assignedTo']['id']][$item] = $value;
        }
        if (!is_array($listAsset) || !isset($listAsset) || count($listAsset) === 0) {
            return response()->json(Helper::formatStandardApiResponse('error'));
        }

        foreach ($listAsset as $userId => $assets) {
            $asset_tag = null;
            $asset_name = null;
            foreach ($assets as $asset) {
                $this->authorize('checkin', Asset::class);
                $this->authorize('checkin', $asset);

                if (is_null($target = $asset->assignedTo)) {
                    return response()->json(Helper::formatStandardApiResponse('error', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkin.already_checked_in')));
                }
                $checkin_at = request('checkin_at', date('Y-m-d H:i:s'));
                $note = request('note', null);
                $checkin_at = null;
                $countAssets = count($assets);
                if ($request->filled('checkin_at')) {
                    $checkin_at = $request->input('checkin_at');
                }
                if ($request->has('status_id')) {
                    $asset->status_id = $request->input('status_id');
                }
                if ($request->status_id == config('enum.status_id.READY_TO_DEPLOY')) {
                    $asset->status_id = config('enum.status_id.ASSIGN');
                }
                if ($asset === end($assets)) {
                    $asset_name .= $asset->name;
                    $asset_tag .= $asset->asset_tag;
                } else {
                    $asset_name .= $asset->name . ", ";
                    $asset_tag .= $asset->asset_tag . ", ";
                }

                if ($asset->checkIn($target, Auth::user(), $checkin_at, $asset->status_id, $note, $asset->name, config('enum.assigned_status.WAITINGCHECKIN'))) {
                    $this->saveAssetHistory($asset->id, config('enum.asset_history.CHECK_IN_TYPE'));
                }
            }
            $data = $this->setDataUser($userId, $asset_name, $countAssets);
            SendCheckinMail::dispatch($data, $data['user_email']);
        }
        return response()->json(Helper::formatStandardApiResponse('success', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkin.success')));
    }

    public function checkin(Request $request, $asset_id)
    {
        $this->authorize('checkin', Asset::class);
        $asset = Asset::findOrFail($asset_id);
        $this->authorize('checkin', $asset);

        if (is_null($target = $asset->assignedTo)) {
            return response()->json(Helper::formatStandardApiResponse('error', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkin.already_checked_in')));
        }
        $checkin_at = request('checkin_at', date('Y-m-d H:i:s'));
        $note = request('note', null);
        $user = $asset->assignedTo;
        $countAssets = 1;
        $checkin_at = null;
        if ($request->filled('checkin_at')) {
            $checkin_at = $request->input('checkin_at');
        }
        if ($request->has('status_id')) {
            $asset->status_id = $request->input('status_id');
        }
        if ($request->status_id == config('enum.status_id.READY_TO_DEPLOY')) {
            $asset->status_id = config('enum.status_id.ASSIGN');
        }
        $asset_name = $asset->name;

        if ($asset->checkIn($target, Auth::user(), $checkin_at, $asset->status_id, $note, $asset->name, config('enum.assigned_status.WAITINGCHECKIN'))) {
            $this->saveAssetHistory($asset_id, config('enum.asset_history.CHECK_IN_TYPE'));
            $data = $this->setDataUser($user->id, $asset_name, $countAssets);


            SendCheckinMail::dispatch($data, $data['user_email']);
            return response()->json(Helper::formatStandardApiResponse('success', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkin.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkin.error')));
    }

    public function checkout(AssetCheckoutRequest $request, $asset_id)
    {
        $this->authorize('checkout', Asset::class);
        $asset = Asset::findOrFail($asset_id);

        if (!$asset->availableForCheckout()) {
            return response()->json(Helper::formatStandardApiResponse('error', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkout.not_available')));
        }

        $this->authorize('checkout', $asset);

        $error_payload = [];
        $error_payload['asset'] = [
            'id' => $asset->id,
            'asset_tag' => $asset->asset_tag,
        ];

        if (request('checkout_to_type') == 'user') {
            // Fetch the target and set the asset's new location_id
            $target = User::find(request('assigned_user'));
            $asset->location_id = (($target) && (isset($target->location_id))) ? $target->location_id : '';
            $error_payload['target_id'] = $request->input('assigned_user');
            $error_payload['target_type'] = 'user';
        }

        if (!isset($target)) {
            return response()->json(Helper::formatStandardApiResponse('error', $error_payload, 'Checkout target for asset ' . e($asset->asset_tag) . ' is invalid - ' . $error_payload['target_type'] . ' does not exist.'));
        }

        $checkout_at = request('checkout_at', date("Y-m-d H:i:s"));
        $expected_checkin = request('expected_checkin', null);
        $note = request('note', null);
        $asset_name = request('name', null);

        // Set the location ID to the RTD location id if there is one
        // Wait, why are we doing this? This overrides the stuff we set further up, which makes no sense.
        // TODO: Follow up here. WTF. Commented out for now. 

        $user = User::find($request->assigned_user);
        $user_email = $user->email;
        $user_name = $user->first_name . ' ' . $user->last_name;
        $current_time = Carbon::now();
        $location = Location::find($asset->location_id);
        $location_address = null;

        // concat asset's address information
        $location_arr = array();

        if (!is_null($location)) {
            if (!is_null($location->address2)) {
                array_push($location_arr, $location->address2);
            }

            if (!is_null($location->address)) {
                array_push($location_arr, $location->address);
            }

            if (!is_null($location->state)) {
                array_push($location_arr, $location->state);
            }

            if (!is_null($location->city)) {
                array_push($location_arr, $location->city);
            }
        }

        foreach ($location_arr as $value) {
            if ($value === end($location_arr)) {
                $location_address .= $value . '.';
            } else {
                $location_address .= $value . ', ';
            }
        }

        //        if ((isset($target->rtd_location_id)) && ($asset->rtd_location_id!='')) {
        //            $asset->location_id = $target->rtd_location_id;
        //        }

        $asset->status_id = config('enum.status_id.ASSIGN');

        if ($asset->checkOut($target, Auth::user(), $checkout_at, $expected_checkin, $note, $asset->name, $asset->location_id, config('enum.assigned_status.WAITINGCHECKOUT'))) {
            $this->saveAssetHistory($asset_id, config('enum.asset_history.CHECK_OUT_TYPE'));
            $data = [
                'user_name' => $user_name,
                'asset_name' => $asset->name,
                'count' => 1,
                'location_address' => $location_address,
                'time' => $current_time->format('d-m-Y'),
                'link' => config('client.my_assets.link'),
            ];

            SendCheckoutMail::dispatch($data, $user_email);
            return response()->json(Helper::formatStandardApiResponse('success', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkout.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', ['asset' => e($asset->asset_tag)], trans('admin/hardware/message.checkout.error')));
    }

    /**
     * Checkin an asset by asset tag
     *
     * @author [A. Janes] [<ajanes@adagiohealth.org>]
     * @since [v6.0]
     * @return JsonResponse
     */
    public function checkinByTag(Request $request)
    {
        $this->authorize('checkin', Asset::class);
        $asset = Asset::where('asset_tag', $request->input('asset_tag'))->first();

        if ($asset) {
            return $this->checkin($request, $asset->id);
        }

        return response()->json(Helper::formatStandardApiResponse('error', [
            'asset' => e($request->input('asset_tag'))
        ], 'Asset with tag ' . e($request->input('asset_tag')) . ' not found'));
    }


    /**
     * Mark an asset as audited
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param int $id
     * @since [v4.0]
     * @return JsonResponse
     */
    public function audit(Request $request)

    {
        $this->authorize('audit', Asset::class);
        $rules = [
            'asset_tag' => 'required',
            'location_id' => 'exists:locations,id|nullable|numeric',
            'next_audit_date' => 'date|nullable',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $validator->errors()->all()));
        }

        $settings = Setting::getSettings();
        $dt = Carbon::now()->addMonths($settings->audit_interval)->toDateString();

        $asset = Asset::where('asset_tag', '=', $request->input('asset_tag'))->first();


        if ($asset) {
            // We don't want to log this as a normal update, so let's bypass that
            $asset->unsetEventDispatcher();
            $asset->next_audit_date = $dt;

            if ($request->filled('next_audit_date')) {
                $asset->next_audit_date = $request->input('next_audit_date');
            }

            // Check to see if they checked the box to update the physical location,
            // not just note it in the audit notes
            if ($request->input('update_location') == '1') {
                $asset->location_id = $request->input('location_id');
            }

            $asset->last_audit_date = date('Y-m-d H:i:s');

            if ($asset->save()) {
                $log = $asset->logAudit(request('note'), request('location_id'));

                return response()->json(Helper::formatStandardApiResponse('success', [
                    'asset_tag' => e($asset->asset_tag),
                    'note' => e($request->input('note')),
                    'next_audit_date' => Helper::getFormattedDateObject($asset->next_audit_date),
                ], trans('admin/hardware/message.audit.success')));
            }
        }

        return response()->json(Helper::formatStandardApiResponse('error', ['asset_tag' => e($request->input('asset_tag'))], 'Asset with tag ' . e($request->input('asset_tag')) . ' not found'));
    }



    /**
     * Returns JSON listing of all requestable assets
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @return JsonResponse
     */
    public function requestable(Request $request)
    {
        $this->authorize('viewRequestable', Asset::class);

        $assets = Company::scopeCompanyables(Asset::select('assets.*'), 'company_id', 'assets')
            ->with(
                'location',
                'assetstatus',
                'assetlog',
                'company',
                'defaultLoc',
                'assignedTo',
                'model.category',
                'model.manufacturer',
                'model.fieldset',
                'supplier'
            )->requestableAssets();

        $offset = request('offset', 0);
        $limit = $request->input('limit', 50);
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        if ($request->filled('search')) {
            $assets->TextSearch($request->input('search'));
        }

        switch ($request->input('sort')) {
            case 'model':
                $assets->OrderModels($order);
                break;
            case 'model_number':
                $assets->OrderModelNumber($order);
                break;
            case 'category':
                $assets->OrderCategory($order);
                break;
            case 'manufacturer':
                $assets->OrderManufacturer($order);
                break;
            default:
                $assets->orderBy('assets.created_at', $order);
                break;
        }

        $total = $assets->count();
        $assets = $assets->skip($offset)->take($limit)->get();

        return (new AssetsTransformer)->transformRequestedAssets($assets, $total);
    }

    #Region "saveAssetHistory"

    /**
     * Save asset history
     */

    private function saveAssetHistory($asset_id, $type)
    {
        $asset = Asset::find($asset_id);

        $history = AssetHistory::create([
            'creator_id' => Auth::user()->id,
            'type' => $type,
            'assigned_to' => $asset->assigned_to,
            'user_id' => $asset->user_id
        ]);
        AssetHistoryDetail::create([
            'asset_histories_id' => $history->id,
            'asset_id' => $asset_id
        ]);
    }
    #End Region

    private function setDataUser($user, $asset_name, $countAssets)
    {

        $user = User::find($user);
        $user_email = $user->email;
        $user_name = $user->first_name . ' ' . $user->last_name;
        $current_time = Carbon::now();
        $location = Location::find($user->location_id ? $user->location_id : env('DEFAULT_LOCATION_USER'));
        $location_address = $location->name;

        $location_arr = array();

        if (!is_null($location)) {
            if (!is_null($location->address2)) {
                array_push($location_arr, $location->address2);
            }

            if (!is_null($location->address)) {
                array_push($location_arr, $location->address);
            }
            if (!is_null($location->state)) {
                array_push($location_arr, $location->state);
            }

            if (!is_null($location->city)) {
                array_push($location_arr, $location->city);
            }
        }

        foreach ($location_arr as $value) {
            if ($value === end($location_arr)) {
                $location_address .= $value . '.';
            } else {
                $location_address .= ' ' . $value . ', ';
            }
        }

        $data = [
            'user_name' => $user_name,
            'asset_name' => $asset_name,
            'count' => $countAssets,
            'user_email' => $user_email,
            'location_address' => $location_address,
            'time' => $current_time->format('d-m-Y'),
            'link' => config('client.my_assets.link'),
        ];

        return $data;
    }
}
