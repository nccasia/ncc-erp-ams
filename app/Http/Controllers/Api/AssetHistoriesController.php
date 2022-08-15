<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetHistoryDetail;
use Illuminate\Http\Request;

class AssetHistoriesController extends Controller
{
    public function index(Request $request)
    {
        // for paginate : offset, limit
        // for sort : sort, order: asc - desc

        // show all when api have NO params
        // $histories = AssetHistoryDetail::query();
        $histories = AssetHistoryDetail::select('asset_history_details.*');

        $histories->with(
            'asset',
            'asset_history',
            'asset.location:id,name',
            'asset.assetstatus:id,name',
            'asset.model.category:id,name',
            'asset.assignedTo:first_name,last_name'

        )->join('assets', 'asset_history_details.asset_id', '=', 'assets.id')
            ->join('asset_histories', 'asset_history_details.asset_histories_id', '=', 'asset_histories.id');

        if ($request->filled('type')) {
            $histories->where('type', $request->input('type'));
        }

        if ($request->filled('date_from')) {
            $histories->whereDate('asset_history_details.created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $histories->whereDate('asset_history_details.created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('location_id')) {
            $histories->where('rtd_location_id', $request->input('location_id'));
        }

        if ($request->filled('model_id')) {
            $histories->InModelList([$request->input('model_id')]);
        }

        if ($request->filled('category_id')) {
            $histories->InCategory($request->input('category_id'));
        }

        if ($request->filled('assigned_status')) {
            $histories->where('assigned_status', '=', $request->input('assigned_status'));
        }

        if ($request->filled('status_id')) {
            $histories->where('status_id', '=', $request->input('status_id'));
        }

        if ($request->filled('search')) {
            $histories->TextSearch($request->input('search'));
        }

        $allowed_columns = [
            'id',
            'created_at',
            'name',
            'asset_tag',
            'rtd_location',
            'category',
            'assigned_to'
        ];

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        // This is kinda gross, but we need to do this because the Bootstrap Tables
        // API passes custom field ordering as custom_fields.fieldname, and we have to strip
        // that out to let the default sorter below order them correctly on the assets table.
        $sort_override = str_replace('custom_fields.', '', $request->input('sort'));

        // This handles all of the pivot sorting (versus the assets.* fields
        // in the allowed_columns array)
        $column_sort = in_array($sort_override, $allowed_columns) ? $sort_override : 'created_at';

        switch ($sort_override) {
            case 'id':
                $histories->OrderIds($order);
                break;
            case 'created_at':
                $histories->OrderCreatedAt($order);
                break;
            case 'name':
                $histories->OrderName($order);
            case 'asset_tag':
                $histories->OrderAssetTag($order);
            case 'rtd_location':
                $histories->OrderRtdLocation($order);
                break;
            case 'category':
                $histories->OrderCategory($order);
                break;
            case 'assigned_to':
                $histories->OrderAssignedTo($order);
                break;
            default:
                $priority_assign_status = config('enum.assigned_status.WAITING');
                $histories->orderByRaw(
                    "CASE WHEN assets.assigned_status = $priority_assign_status THEN 1 ELSE 0 END DESC"
                );
                $histories->orderBy($column_sort, $order);
                break;
        }


        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($histories->get()) && ($request->get('offset') > $histories->count())) ? $histories->count() : $request->get('offset', 0);

        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $histories = ($request->filled('offset') && $request->filled('limit'))
            ? $histories->skip($offset)->take($limit)->get() : $histories->get();


        return $histories;
    }
}
