<?php

namespace App\Services;

use App\Domains\Finfast\Services\FinfastService;
use App\Models\CustomField;
use App\Models\FinfastRequest;
use App\Models\FinfastRequestAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinfastRequestService
{
    protected $finfastService;
    public function __construct(FinfastService  $finfastService)
    {
        $this->finfastService = $finfastService;
    }

    public function getList(Request $request){

        $allowed_columns = [
            'id',
            'name',
            'status',
            'branch_id',
            'supplier_id',
            'entry_id',
            'note',
            'created_at',
            'updated_at',
        ];


        $filter = [];

        if ($request->filled('filter')) {
            $filter = json_decode($request->input('filter'), true);
        }

        $all_custom_fields = CustomField::all(); //used as a 'cache' of custom fields throughout this page load
        foreach ($all_custom_fields as $field) {
            $allowed_columns[] = $field->db_column_name();
        }

        $requests = FinfastRequest::with('finfast_request_assets.asset');

        // Search custom fields by column name
        foreach ($all_custom_fields as $field) {
            if ($request->filled($field->db_column_name())) {
                $requests->where($field->db_column_name(), '=', $request->input($field->db_column_name()));
            }
        }

        if ($request->filled('name')) {
            $requests->where('finfast_requests.name', '=', $request->input('name'));
        }
        if ($request->filled('status')) {
            $requests->where('finfast_requests.status', '=', $request->input('status'));
        }
        if ($request->filled('branch_id')) {
            $requests->where('finfast_requests.branch_id', '=', $request->input('branch_id'));
        }
        if ($request->filled('supplier_id')) {
            $requests->where('finfast_requests.supplier_id', '=', $request->input('supplier_id'));
        }
        if ($request->filled('entry_id')) {
            $requests->where('finfast_requests.entry_id', '=', $request->input('entry_id'));
        }
        if ($request->filled('note')) {
            $requests->where('finfast_requests.note', '=', $request->input('note'));
        }

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($requests) && ($request->get('offset') > $requests->count())) ? $requests->count() : $request->get('offset', 0);

        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit'))) ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';

        // This is kinda gross, but we need to do this because the Bootstrap Tables
        // API passes custom field ordering as custom_fields.fieldname, and we have to strip
        // that out to let the default sorter below order them correctly on the assets table.
        $sort_override = str_replace('custom_fields.', '', $request->input('sort'));

        // This handles all of the pivot sorting (versus the assets.* fields
        // in the allowed_columns array)
        $column_sort = in_array($sort_override, $allowed_columns) ? $sort_override : 'assets.created_at';


        switch ($sort_override) {
            default:
                $requests = $requests->orderBy($column_sort, $order);
                break;
        }

        if ((! is_null($filter)) && (count($filter)) > 0) {
            $requests->ByFilter($filter);
        } elseif ($request->filled('search')) {
            $requests = $requests->TextSearch($request->input('search'));
        }

        $total = $requests->count();

        $requests = $requests->skip($offset)->take($request->input('limit'))->get();

        /**
         * Include additional associated relationships
         */
        if ($request->input('components')) {
            $requests->loadMissing(['components' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }]);
        }

        $data['total'] = $total;
        $data['rows'] =  $this->mapValueInListRequest($requests);

        return $data;
    }

    public function create($requestModel, $asset_ids){
        return DB::transaction(function () use ($requestModel, $asset_ids) {
            $requestModel->save();
            $this->saveListRequestAsset($requestModel->id, $asset_ids);
        });
    }

    public function delete($id){
      $finfastRequest = FinfastRequest::with('finfast_request_assets')->find($id);

      if (!$finfastRequest) return false;

      if ($finfastRequest->status !== config('enum.request_status.PENDING'))
              return false;

      $this->deleteListRequestAsset($finfastRequest->finfast_request_assets);

      $finfastRequest->delete();

      return true;
    }

    public function show($id){
        $request =  FinfastRequest::with("finfast_request_assets.asset")->find($id);
        $request->supplier = $this->finfastService->findSupplier($request->supplier_id);
        $request->branch = $this->finfastService->findBranch($request->branch_id);
        $request->entry_type = $this->finfastService->findEntryType($request->entry_id);
        return $request;

    }


    public function saveListRequestAsset($request_id, $asset_ids){
        foreach ($asset_ids as $item){
            $request_asset = new FinfastRequestAsset();
            $request_asset->asset_id = $item;
            $request_asset->finfast_request_id = $request_id;
            $request_asset->save();
        }
    }

    public function deleteListRequestAsset($finfast_request_assets){
        foreach ($finfast_request_assets as $item){
            $finfast_request_asset = FinfastRequestAsset::find($item->id);
            $finfast_request_asset -> delete();
        }
    }

    public function mapValueInListRequest($listRequest){
        return $listRequest->map(function ($item) {
            $item->supplier = $this->finfastService->findSupplier($item->supplier_id);
            $item->branch = $this->finfastService->findBranch($item->branch_id);
            $item->entry_type = $this->finfastService->findEntryType($item->entry_id);
            return $item;
        });
    }
}
