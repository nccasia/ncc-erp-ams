<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetHistoryDetail;
use Illuminate\Http\Request;

class AssetHistoriesController extends Controller
{
    public function index(Request $request)
    {
        // for search
        $type = $request->assetHistoryType;
        $from = $request->purchaseDateFrom;
        $to = $request->purchaseDateTo;
        $location = $request->location;
        $category = $request->category_id;

        // for paginate
        $offset = $request->offset;
        $limit = $request->limit;

        // show all when api have NO params
        $histories = AssetHistoryDetail::with(['asset', 'asset_history', 'asset_history.user:id,first_name,last_name,username']);

        if (!is_null($type) || !is_null($from) || !is_null($to) || !is_null($location) || !is_null($category)) {
            $assetHistoryQuery = AssetHistoryDetail::whereHas('asset_history', function ($query) use ($type,$from, $to) {
                $query->where('type', $type);
                if (!is_null($from)) {
                    $query->whereDate('created_at' ,'>=', $from);
                }

                if (!is_null($to)) {
                    $query->whereDate('created_at' ,'<=', $to);
                }
            });

            if (!is_null($type)) {
                // show histories depend on type when api only have 'assetHistoryType' param
                $histories = $assetHistoryQuery->with(['asset', 'asset_history','asset_history.user:id,first_name,last_name,username']);

                if (!is_null($from) || !is_null($to) || !is_null($location) || !is_null($category)) {
                    $histories = $assetHistoryQuery
                        ->whereHas('asset', function ($query) use ( $location, $category) {
                            if (!is_null($location)) {
                                $query->where('rtd_location_id', $location);
                            }
                            
                            if (!is_null($category)) {
                                $query->join('models', 'assets.model_id', '=', 'models.id')
                                    ->select('category_id')
                                    ->where('category_id', $category);
                            }
                        })->with(['asset', 'asset_history','asset_history.user:id,first_name,last_name,username']);
                }
            } else {
                // when params do not have 'assetHistoryType' param
                $histories = AssetHistoryDetail::whereHas('asset', function ($query) use ($from, $to, $location, $category) {
                    if (!is_null($from)) {
                        $query->where('purchase_date', '>=', $from);
                    }

                    if (!is_null($to)) {
                        $query->where('purchase_date', '<=', $to);
                    }

                    if (!is_null($location)) {
                        $query->where('rtd_location_id', $location);
                    }

                    if (!is_null($category)) {
                        $query->join('models', 'assets.model_id', '=', 'models.id')
                            ->select('category_id')
                            ->where('category_id', $category);
                    }
                })->with(['asset', 'asset_history','asset_history.user:id,first_name,last_name,username']);
            }
        }

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($histories->get()) && ($offset > $histories->count())) ? $histories->count() : $offset;
        
        // Check to make sure the limit is not higher than the max allowed
        $limit = ((config('app.max_results') >= $limit) && $limit) ? $limit : config('app.max_results');
        $histories = (!is_null($offset) && !is_null($limit)) ? $histories->skip($offset)->take($limit)->get() : $histories->get();
        
        return $histories;
    }
}
