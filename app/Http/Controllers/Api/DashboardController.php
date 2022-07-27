<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                $locations
            );

            return response()->json(Helper::formatStandardApiResponse('success', $locations, trans('admin/dashboard/message.success')));
        }
        else  return response()->json(Helper::formatStandardApiResponse('error', null , trans('admin/dashboard/message.not_permission')),401);
    }

    public function reportAssetByType(Request $request)
    {
        $query = 'SELECT g.*, l.name as locationName
        FROM
          (SELECT g.location_id, g.name,
            sum(CASE
                WHEN g.type = 0 THEN g.total            
                ELSE 0
            end) AS checkout,
            sum(CASE
                WHEN g.type = 1 THEN g.total
                ELSE 0
            end) AS checkin
           FROM
             (SELECT assets.location_id,
                     history.type,
                     c.name,
                     COUNT(*) AS total
              FROM asset_histories AS history
              JOIN asset_history_details AS history_details ON history.id = history_details.asset_histories_id
              JOIN assets ON assets.id = history_details.asset_id
              JOIN models m ON m.id = assets.model_id
	          JOIN categories c ON c.id = m.category_id';

        $bind = [];
        $from = $request->from;
        $to = $request->to;

        $where = ' WHERE true ';

        if ($from && $to) {
            $where .= " AND history.created_at >= :from
                             AND history.created_at <= :to";
            $bind = ['from' => $from, 'to' => $to];
        }

        if($request->asset_id){
            $where .= " AND history_details.asset_id = :asset_id";
            $bind['asset_id'] = $request->asset_id;
        }


        $query .= $where;
    
        $query .= " GROUP BY assets.location_id, c.name, history.type) AS g
        GROUP BY g.location_id, g.name) AS g
        JOIN locations l ON l.id = g.location_id";

        if (Auth::user()->hasAccess('admin')) {

            $assets_statistic = DB::select(
                $query,
                $bind
            );


            return response()->json(Helper::formatStandardApiResponse('success', $assets_statistic, trans('admin/dashboard/message.success')));
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
