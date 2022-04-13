<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinfastRequestAsset extends Model
{
    use HasFactory;

    protected $fillable = ['asset_id', 'finfast_request_id'];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'finfast_request_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

}
