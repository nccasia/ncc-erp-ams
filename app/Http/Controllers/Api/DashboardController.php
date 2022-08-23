<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 
use App\Models\Location;
use App\Models\Category;


class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Show the page

        if (Auth::user()->hasAccess('admin')) {

            // get all location
            $locations = $this->dashboardService->getAllLocaltions($request->purchase_date_from, $request->purchase_date_to);

            // Calculate total devices by location
            $locations = $this->dashboardService->mapCategoryToLocation($locations);
            
            // Calculate total devices NCC
            $locations = $this->dashboardService->countCategoryOfNCC(
                clone $locations
            );

            return response()->json(Helper::formatStandardApiResponse('success', $locations, trans('admin/dashboard/message.success')));
        }
        else  return response()->json(Helper::formatStandardApiResponse('error', null , trans('admin/dashboard/message.not_permission')),401);
    }

    public function reportAssetByType(Request $request)
    {
        $query = 'SELECT g.*, l.name as location_name
        FROM
          (SELECT g.name as category_name, g.id as category_id, g.rtd_location_id, 
            CAST(
            sum(CASE
                WHEN g.action_type = "checkout" THEN g.total            
                ELSE 0
            end) AS SIGNED ) AS checkout,
            CAST(
            sum(CASE
                WHEN g.action_type = "checkin from" THEN g.total
                ELSE 0
            end) AS SIGNED ) AS checkin
           FROM
             (SELECT assets.rtd_location_id,
                     action_logs.action_type,
                     cates.name,
                     cates.id,
                     COUNT(*) AS total
              FROM action_logs
              JOIN assets ON assets.id = action_logs.item_id
              JOIN models ON models.id = assets.model_id
	          JOIN categories cates ON cates.id = models.category_id';

        $bind = [];
        $from = $request->from;
        $to = $request->to;

        $where = ' WHERE true ';

        if ($from && $to) {
            $where .= " AND cast(action_logs.created_at as date) >= cast(:from as date)
                             AND cast(action_logs.created_at as date) <=  cast(:to as date)";
            $bind = ['from' => $from, 'to' => $to];
        }

        if($request->asset_id){
            $where .= " AND action_logs.item_id = :asset_id";
            $bind['asset_id'] = $request->asset_id;
        }

        
        $query .= $where;
    
        $query .= " GROUP BY assets.rtd_location_id, cates.name, cates.id , action_logs.action_type) AS g
        GROUP BY g.rtd_location_id, g.name , g.id) AS g
        JOIN locations l ON l.id = g.rtd_location_id";

        $locations = Location::select('id','name')->get();
        $categories = Category::select('id','name')->get();



        if (Auth::user()->hasAccess('admin')) {

            $assets_statistic = DB::select(
                $query,
                $bind
            );

            // dd ($assets_statistic);


            return response()->json(
                Helper::formatStandardApiResponse('success',
                [
                    'locations' => $locations,
                    'categories' => $categories,
                    'assets_statistic' => $assets_statistic
                ]
                , trans('admin/dashboard/message.success')));
        } else {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/dashboard/message.not_permission')), 401);
        }
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
