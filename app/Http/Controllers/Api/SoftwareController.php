<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DateFormatter;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\SoftwaresTransformer;
use App\Models\Company;
use App\Models\Software;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoftwareController extends Controller
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

        $softwares = Company::scopeCompanyables(
            Software::select('softwares.*')->with('category', 'manufacturer', 'licenses')
                ->withSum('licenses', 'seats')
                ->withSum('licenses', 'checkout_count')
        );

        $allowed_columns = [
            'id',
            'name',
            'category_id',
            'manufacturer_id',
            'created_at',
            'notes',
            'software_tag',
            'version'
        ];

        $filter = [];
        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        if ((!is_null($filter)) && (count($filter)) > 0) {
            $softwares->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $softwares->TextSearch($request->input('search'));
        }

        if ($request->filled('manufacturer_id')) {
            $softwares->ByManufacturer($request->input('manufacturer_id'));
        }

        $offset = (($softwares) && ($request->get('offset') > $softwares->count()))
            ? $softwares->count()
            : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        if ($request->filled('dateFrom', 'dateTo')) {
            $filterByDate = DateFormatter::formatDate($request->input('dateFrom'), $request->input('dateTo'));
            $softwares->whereBetween('softwares.created_at', [$filterByDate]);
        }

        $sort = $request->input('sort');

        $default_sort = in_array($sort, $allowed_columns) ? $sort : 'softwares.created_at';

        switch ($sort) {
            case 'category':
                $softwares->OrderCategory($order);
                break;

            case 'manufacturer':
                $softwares->OrderManufacturer($order);
                break;
            case 'checkout_count':
                $softwares->OrderCheckoutCount($order);
                break;
            default:
                $softwares->OrderBy($default_sort, $order);
        }
        
        $total = $softwares->count();
        $softwares = $softwares->skip($offset)->take($limit)->get();
        return (new SoftwaresTransformer)->transformSoftwares($softwares, $total);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', Software::class);

        $software = new Software();

        $software->name = $request->get('name');
        $software->software_tag = $request->get('software_tag');
        $software->version = $request->get('version');
        $software->category_id = $request->get('category_id');
        $software->manufacturer_id = $request->get('manufacturer_id');
        $software->notes = $request->get('notes');
        $software->user_id = Auth::id();

        if ($software->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $software, trans('admin/softwares/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $software->getErrors()));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return array
     */
    public function show($id)
    {
        $this->authorize('view', Software::class);
        $software = Software::withCount('licenses as total_licenses')->with('licenses')->findOrFail($id);

        return (new SoftwaresTransformer)->transformSoftware($software);
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
        $this->authorize('update', Software::class);

        $software = Software::find($id);
        if ($software) {
            $software->fill($request->all());
            if ($software->save()) {
                return response()->json(Helper::formatStandardApiResponse('success', $software, trans('admin/softwares/message.update.success')));
            }
            return response()->json(Helper::formatStandardApiResponse('error', null, $software->getErrors()));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/softwares/message.does_not_exist')));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $software = Software::findOrFail($id);

        $this->authorize('delete', $software);
        if ($software->delete()) {
            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/softwares/message.delete.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/softwares/message.does_not_exist')));
    }
}