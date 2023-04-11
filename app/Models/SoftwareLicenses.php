<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Watson\Validating\ValidatingTrait;

class SoftwareLicenses extends Model
{
    use HasFactory, ValidatingTrait;

    public $timestamps = true;
    protected $guarded = 'id';
    protected $table = 'software_licenses';

    protected $rules =[
        'software_id' => 'required|exists:softwares,id',
        'licenses' => 'required',
        'seats' => 'required|min:1|integer',
        'purchase_date' => 'required',
        'purchase_cost' => 'required',
        'expiration_date' => 'required',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'expiration_date' => 'datetime',
        'seats'   => 'integer',
        'software_id'   => 'integer',
    ];
    protected $fillable = [
        'company_id',
        'expiration_date',
        'purchase_cost',
        'purchase_date',
        'seats',
        'user_id',
        'software_id'
    ];

    public function software(){
        return $this->belongsTo(Software::class, 'software_id');
    }

    public function scopeOrderSoftware($query, $order){
        return $query->join('softwares', 'software_licenses.software_id', '=', 'softwares.id')->orderBy('softwares.name', $order);
    }

    public function freeSeats(){
        return $this->hasMany(SoftwareLicenses::class)
        ->whereNull('deleted_at'); 
    }
    
}
