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
        } else  return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/dashboard/message.not_permission')), 401);
    }

    public function reportAssetByType(Request $request)
    {
        $bind = [];
        $from = $request->from;
        $to = $request->to;

        if ($from && $to) {
            $bind = ['from' => $from, 'to' => $to];
        }

        $locations = Location::select('id', 'name')->get();
        $categories = Category::select('id', 'name', 'category_type')->get();

        if (Auth::user()->hasAccess('admin')) {

            $assets_statistic = DB::select(
                $this->dashboardService->queryReportAssetByType('assets', 'App\\\Models\\\Asset','rtd_location_id', $from, $to),
                $bind
            );

            $consumables_statistic = DB::select(
                $this->dashboardService->queryReportAssetByType('consumables', 'App\\\Models\\\Consumable', 'location_id', $from, $to),
                $bind
            );

            $accessories_statistic = DB::select(
                $this->dashboardService->queryReportAssetByType('accessories', 'App\\\Models\\\Accessory','location_id', $from, $to),
                $bind
            );


            return response()->json(
                Helper::formatStandardApiResponse(
                    'success',
                    [
                        'locations' => $locations,
                        'categories' => $categories,
                        'assets_statistic' => $assets_statistic,
                        'consumables_statistic' => $consumables_statistic,
                        'accessories_statistic' => $accessories_statistic
                    ],
                    trans('admin/dashboard/message.success')
                )
            );
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
