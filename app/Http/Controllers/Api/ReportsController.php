<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\ActionlogsTransformer;
use App\Models\Actionlog;
use App\Models\Category;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    /**
     * Returns Activity Report JSON.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @return View
     */
    public function index(Request $request)
    {
        $this->authorize('reports.view');
        $category = Category::find($request->category_id);
        $actionlogs = Actionlog::select('action_logs.*')
            ->with('item', 'user', 'target', 'location')
            ->leftJoin('consumables', 'action_logs.item_id', '=', 'consumables.id')
            ->leftJoin('accessories', 'action_logs.item_id', '=', 'accessories.id')
            ->leftJoin('assets', 'action_logs.item_id', '=', 'assets.id');

        if ($request->filled('search')) {
            $actionlogs = $actionlogs->TextSearch($request->input('search'));
        }

        if (($request->filled('target_type')) && ($request->filled('target_id'))) {
            $actionlogs = $actionlogs->where('target_id', '=', $request->input('target_id'))
                ->where('target_type', '=', 'App\\Models\\' . ucwords($request->input('target_type')));
        }

        if (($request->filled('item_type')) && ($request->filled('item_id'))) {
            $actionlogs = $actionlogs->where('item_id', '=', $request->input('item_id'))
                ->where('item_type', '=', 'App\\Models\\' . ucwords($request->input('item_type')));
        }

        if ($request->filled('action_type')) {
            $actionlogs = $actionlogs->where('action_type', '=', $request->input('action_type'));
        }

        if ($request->filled('date_from')) {
            $actionlogs->whereDate('action_logs.created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $actionlogs->whereDate('action_logs.created_at', '<=', $request->input('date_to'));
        }
        if ($category){
            if($category->category_type == 'asset'){
                $actionlogs->leftJoin('models', 'assets.model_id', '=', 'models.id')
                           ->where('models.category_id', $request->input('category_id'));
                if ($request->filled('location_id')) {
                    $actionlogs->where('assets.rtd_location_id', $request->input('location_id'));
                    $actionlogs->where('models.category_id', $request->input('category_id'));
                }
            }
            if( $category->category_type == 'consumable'){
                $actionlogs->where('consumables.category_id', $request->input('category_id'));
                if ($request->filled('location_id')) {
                    $actionlogs->where('consumables.location_id', $request->input('location_id'));
                    $actionlogs->where('consumables.category_id', $request->input('category_id'));
                }
            }
            if( $category->category_type == 'accessory'){
                $actionlogs->where('accessories.category_id', $request->input('category_id'));
                if ($request->filled('location_id')) {
                    $actionlogs->where('accessories.location_id', $request->input('location_id'));
                    $actionlogs->where('accessories.category_id', $request->input('category_id'));
                }
            }
        }
        else {
            if ($request->filled('location_id')) {
                $actionlogs->where('assets.rtd_location_id', $request->input('location_id'));
                $actionlogs->orWhere('accessories.location_id', $request->input('location_id'));
                $actionlogs->orWhere('consumables.location_id', $request->input('location_id'));
            }
        }
        if ($request->filled('category_type')) {
            $actionlogs->where('action_logs.item_type', "App\\Models\\" . $request->input('category_type'));
        }

        // For sort
        $allowed_columns = [
            'id',
            'created_at',
            'target_id',
            'user_id',
            'accept_signature',
            'action_type',
            'note',
        ];
        
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';
        $order = ($request->input('order') == 'asc') ? 'asc' : 'desc';
        $actionlogs = $actionlogs->orderBy($sort, $order);

        // for pagination
        $total = $actionlogs->count();

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = ($request->get('offset') > $total) ? $total : $request->get('offset', 0);

        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $actionlogs = ($request->filled('offset') && $request->filled('limit'))
            ? $actionlogs->skip($offset)->take($limit)->get() : $actionlogs->get();

        return response()->json((new ActionlogsTransformer)->transformActionlogs($actionlogs, $total), 200, ['Content-Type' => 'application/json;charset=utf8'], JSON_UNESCAPED_UNICODE);
    }
}