<?php

namespace App\Http\Controllers\Api;

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
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('view', Software::class);

        $softwares = Company::scopeCompanyables(Software::select('softwares.*')->with('category', 'manufacturer')->withCount('totalLicenses as total_licenses'));

        $allowed_columns = [
            'id',
            'name',
            'category_id',
            'munufacturer_id',
            'total_licenses',
            'created_at',
            'notes',
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

        $total = $softwares->count();
        $offset = (($softwares) && ($request->get('offset') > $softwares->count()))
            ? $softwares->count()
            : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        if ($request->filled('dateFrom', 'dateTo')) {
            $softwares->whereBetween('softwares.created_at', [$request->input('dateFrom'), $request->input('dateTo')]);
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
                
            default:
                $softwares->OrderBy($default_sort, $order);
        }

        $softwares = $softwares->skip($offset)->take($limit)->get();
        return (new SoftwaresTransformer)->transformSoftwares($softwares, $total);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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
            return response()->json(Helper::formatStandardApiResponse('success', $software, trans('admin/licenses/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $software->getErrors()));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', Software::class);
        $software = Software::withCount('totalLicenses as total_licenses')->with('softwareLicenses')->findOrFail($id);

        return (new SoftwaresTransformer)->transformSoftware($software);
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
        $this->authorize('update', License::class);

        $software = Software::find($id);
        if ($software) {
            $software->fill($request->all());
            if ($software->save()) {
                return response()->json(Helper::formatStandardApiResponse('success', $software, trans('admin/software/message.update.success')));
            }
            return response()->json(Helper::formatStandardApiResponse('error', null, $software->getErrors()), 200);
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/software/message.does_not_exist')), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $software = Software::findOrFail($id);

        $this->authorize('delete', $software);
        if($software->delete()){
            return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/software/message.delete.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/software/message.does_not_exist')), 200);

    }
}
