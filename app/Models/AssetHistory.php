<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    use HasFactory;

    protected $table = 'asset_histories';
    protected $fillable = [
        'creator_id',
        'user_id',
        'type'
    ];
}
