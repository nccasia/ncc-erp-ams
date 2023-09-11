<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\AssetHistoriesTransformer;
use App\Services\AssetReportService;
use Illuminate\Http\Request;

class AssetReportController extends Controller
{
    protected $assetReportService;

    public function __construct(AssetReportService $assetReportService)
    {
        $this->assetReportService = $assetReportService;
    }

    public function getAssetHistory($asset_id)
    {
        $assetHistories = $this->assetReportService->getAssetHistory($asset_id);
        return (new AssetHistoriesTransformer)->transformAssetHistories($assetHistories);
    }
}
