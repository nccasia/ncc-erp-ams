<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\SoftwaresTransformer;
use App\Models\Company;
use App\Models\Software;
use Illuminate\Http\Request;

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

        $sort = str_replace('custom_fields.', '', $request->input('sort'));

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
