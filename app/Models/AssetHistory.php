<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    use HasFactory;

    protected $table = 'asset_histories';
    protected $fillable = [
        'assigned_to',
        'user_id',
        'type'
    ];

    public function assigned()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
