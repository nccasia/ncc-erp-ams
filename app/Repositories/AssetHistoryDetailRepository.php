<?php

namespace App\Repositories;

use App\Models\AssetHistoryDetail;
use Illuminate\Support\Facades\Auth;

class AssetHistoryDetailRepository
{
    private $assetHistoryDetail;

    public function __construct(AssetHistoryDetail $assetHistoryDetail)
    {
        $this->assetHistoryDetail = $assetHistoryDetail;
    }

    public function store($data)
    {
        $this->assetHistoryDetail->create([
            'asset_histories_id' => $data['history_id'],
            'asset_id'           => $data['asset_id'],
        ]);
    }
}
