<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetHistoryDetail extends Model
{
    use HasFactory;

    protected $table = 'asset_history_details';
    protected $fillable = [
        'asset_histories_id',
        'asset_id'
    ];

    public function asset_history()
    {
        return $this->belongsTo(\App\Models\AssetHistory::class, 'asset_histories_id');
    }
}
