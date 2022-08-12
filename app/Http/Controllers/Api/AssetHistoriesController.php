<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetHistoryDetail;
use Illuminate\Http\Request;
use App\Models\Company;

class AssetHistoriesController extends Controller
{
    public function index(Request $request)
    {
        // for seach : type, date_from, date_to, location_id, category_id, model_id, assigned_status, status_id
        // for paginate : offset, limit
        // for sort : sort, order: asc - desc

        // show all when api have NO params
        $histories = AssetHistoryDetail::query();

        $histories->join('assets', 'asset_history_details.asset_id', '=', 'assets.id')
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

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($histories->get()) && ($request->get('offset') > $histories->count())) ? $histories->count() : $request->get('offset', 0);

        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $get_histories = [
            'asset_history_details.*',
            'assets.name', 'assets.asset_tag', 'assets.notes',
            'asset_histories.assigned_to', 'asset_histories.user_id', 'asset_histories.type'
        ];

        $histories = ($request->filled('offset') && $request->filled('limit'))
            ? $histories->skip($offset)->take($limit)->get($get_histories) : $histories->get($get_histories);

        return $histories;
    }
}
