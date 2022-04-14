<?php

namespace App\Services;

use App\Domains\Finfast\Services\FinfastService;
use App\Models\Category;
use App\Models\FinfastRequest;
use App\Models\FinfastRequestAsset;
use App\Models\ListRequest;
use App\Models\Location;
use App\Models\RequestAsset;
use App\Models\Statuslabel;
use Illuminate\Support\Facades\DB;

class FinfastRequestService
{
    protected $finfastService;
    public function __construct(FinfastService  $finfastService)
    {
        $this->finfastService = $finfastService;
    }

    public function getList(){

        $requests = FinfastRequest::orderBy('created_at','DESC')->with('finfast_request_assets')->get();
        return $this->mapValueInListRequest($requests);
    }

    public function create($requestModel, $asset_ids){
        return DB::transaction(function () use ($requestModel, $asset_ids) {
            $requestModel->save();
            $this->saveListRequestAsset($requestModel->id, $asset_ids);
        });
    }

    public function saveListRequestAsset($request_id, $asset_ids){
        foreach ($asset_ids as $item){
            $request_asset = new FinfastRequestAsset();
            $request_asset->asset_id = $item;
            $request_asset->finfast_request_id = $request_id;
            $request_asset->save();
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
