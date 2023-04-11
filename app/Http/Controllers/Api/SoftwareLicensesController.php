<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\SoftwareLicensesTransformer;
use App\Models\Company;
use App\Models\SoftwareLicenses;
use Illuminate\Http\Request;

class SoftwareLicensesController extends Controller
{
    /**
     * Display a listing of the resource by software.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $softwareId)
    {
        $this->authorize('view', SoftwareLicenses::class);
        $licenses = Company::scopeCompanyables(SoftwareLicenses::select('software_licenses.*')
        ->with('software')->where('software_id', '=', $softwareId)
        // ->withCount('freeSeats')
        );
        $allowed_columns = [
            'id',
            'software_id',
            'licenses',
            'seats',
            'freeSeats',
            'purchase_date',
            'expiration_date',
            'purchase_cost'
        ];

        $total = $licenses->count();
        $offset = (($licenses) && ($request->get('offset') > $licenses->count()))
            ? $licenses->count()
            : $request->get('offset', 0);

        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit')))
            ? $limit = $request->input('limit')
            : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        $field_sort = str_replace('custom_fields.', '', $request->input('sort'));

        $default_sort = in_array($field_sort, $allowed_columns) ? $field_sort : 'software_licenses.created_at';

        $licenses->orderBy($default_sort, $order);

        $licenses = $licenses->skip($offset)->take($limit)->get();
        return (new SoftwareLicensesTransformer)->transformSoftwareLicenses($licenses, $total);
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
