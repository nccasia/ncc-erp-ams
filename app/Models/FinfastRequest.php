<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinfastRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'branch_id',
        'supplier_id',
        'entry_id',
        'note'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    public function finfast_request_assets(){
        return $this->hasMany(FinfastRequestAsset::class);
    }

}
