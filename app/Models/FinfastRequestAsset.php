<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinfastRequestAsset extends Model
{
    use HasFactory;
    use Searchable;


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

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

}

