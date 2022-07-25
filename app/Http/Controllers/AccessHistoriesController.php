<?php

namespace App\Http\Controllers;

use App\Models\AssetHistoryDetail;
use Illuminate\Http\Request;

class AccessHistoriesController extends Controller
{
    public function index(Request $request)
    {   
        $type = $request->assetHistoryType;
        if($type != null) {
            $assetAllocated = AssetHistoryDetail::whereHas('asset_history', function ($query) use ($type) {
                return $query->where('type', $type);
            })->with(['asset', 'asset_history'])->get();
        } else {
            $assetAllocated = AssetHistoryDetail::with(['asset', 'asset_history'])->get();
        }
        
        return $assetAllocated;
    }
}
