<?php

namespace App\Http\Controllers\Api;

use App\Domains\Finfast\Services\FinfastService;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinFastRequestRequest;
use App\Services\FinfastRequestService;
use Illuminate\Http\Request;

class FinfastRequestController extends Controller
{
    protected $finfastRequestService;
    protected $finfastService;

    public function __construct(FinfastRequestService $finfastRequestService, FinfastService  $finfastService)
    {
        $this->finfastRequestService = $finfastRequestService;
        $this->finfastService = $finfastService;
    }


    public function index(Request $request)
    {
        return $this->finfastRequestService->getList($request);
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
     * @param  FinfastRequestService  $request
     * @return \Illuminate\Http\Response
     */
    public function store(FinFastRequestRequest $request)
    {

        $requestModel = new \App\Models\FinfastRequest();
        $requestModel->name = $request->name;
        $requestModel->branch_id = $request->branch_id;
        $requestModel->entry_id = $request->entry_id;
        $requestModel->note = $request->note;
        $requestModel->supplier_id = $request->supplier_id;
        $requestModel->status = config('enum.request_status.PENDING');
        $asset_ids = json_decode($request->asset_ids);

        $this->finfastRequestService->create($requestModel, $asset_ids);

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/hardware/message.create.success')));

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $finfast_request = $this->finfastRequestService->show($id);
        return response()->json(Helper::formatStandardApiResponse('success', $finfast_request, trans('admin/hardware/message.create.success')));
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
        $finfast_request = $this->finfastRequestService->delete($id);
        if ($finfast_request)  return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/hardware/message.create.success')));
        else   return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/hardware/message.create.success')));

    }
}
