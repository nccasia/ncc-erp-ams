<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Statuslabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use function Aws\map;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Show the page

        if (Auth::user()->hasAccess('admin')) {

            // get all location
            $locations = $this->getAllLocaltions();

            // Calculate total devices by location
            $locations = $this->mapCategoryToLocation($locations);


           return response()->json(Helper::formatStandardApiResponse('success', $locations, trans('admin/dashboard/message.success')));
        }
        else  return response()->json(Helper::formatStandardApiResponse('error', null , trans('admin/dashboard/message.not_permission')),401);


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

    private function mapCategoryToLocation($locations)
    {

        $categories = Category::select([
            'id',
            'name',
        ])->get();
        return $locations->map(function ($location) use ($categories) {
            return $this->addCategoriesToLocation($location, $categories);
        });
    }

    private function addCategoriesToLocation($location, $categories)
    {
        $categories = $this->mapStatusToCategory($location['assets'], $categories);
        $location['categories'] = $categories;
        return $location;

    }

    private function mapStatusToCategory($assets, $categories)
    {
        $status_labels = Statuslabel::select([
            'id',
            'name',
        ])->get();
        return  $categories->map(function ($category) use ($categories, $status_labels, $assets) {
            $category = clone $this->addStatusToCategory($assets, $category, $status_labels);
            return $category;
        });


    }

    private function addStatusToCategory($assets, $category, $status_labels)
    {
        $assets = (new \App\Models\Asset)->scopeInCategory($assets->toQuery(), $category['id'])->get();
        $category['assets_account'] = count($assets);

        $status_labels = $this->mapValueToStatusLabels($assets, $status_labels);
        $category['status_labels'] = $status_labels;

        return $category;
    }

    private function mapValueToStatusLabels($assets,$status_labels)
    {
        return $status_labels->map(function ($status_label) use ($assets){
            $status_label = clone $this->addValueToStatusLabel($assets, $status_label);
            return $status_label;
        });
    }

    private function addValueToStatusLabel($assets, $status_label)
    {
        $assets_by_status = (new \App\Models\Asset)->getByStatusId($assets, $status_label['id']);
        $status_label['assets_account'] = count($assets_by_status);
        return $status_label;
    }

    private function getTotalAsset()
    {
        $counts['asset'] = \App\Models\Asset::count();
        $counts['accessory'] = \App\Models\Accessory::count();
        $counts['license'] = \App\Models\License::assetcount();
        $counts['consumable'] = \App\Models\Consumable::count();
        $counts['component'] = \App\Models\Component::count();
        $counts['user'] = \App\Models\User::count();
        $counts['grand_total'] = $counts['asset'] + $counts['accessory'] + $counts['license'] + $counts['consumable'];
    }

    private function getAllLocaltions()
    {
        $locations = Location::select([
            'id',
            'name',
        ])->with('assets')->withCount('assets as assets_count')->get();

        return $locations;
    }




}
