<?php

namespace App\Repositories;

use App\Models\AssetHistory;
use Illuminate\Support\Facades\Auth;

class AssetHistoryRepository
{
    private $assetHistory;

    public function __construct(AssetHistory $assetHistory)
    {
        $this->assetHistory = $assetHistory;
    }

    public function store($data)
    {
        return $this->assetHistory->create([
            'creator_id' => Auth::user()->id,
            'type' => $data['type'],
            'assigned_to' => $data['assigned_to'],
            'user_id' => $data['user_id']
        ]);
    }
}
