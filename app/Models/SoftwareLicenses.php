<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

class SoftwareLicenses extends Model
{
    use HasFactory, ValidatingTrait, SoftDeletes, Searchable;

    public $timestamps = true;
    protected $guarded = 'id';
    protected $table = 'software_licenses';

    protected $rules = [
        'software_id' => 'required|exists:softwares,id',
        'licenses' => 'required|unique:software_licenses|min:3',
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
        'expiration_date',
        'purchase_cost',
        'purchase_date',
        'seats',
        'user_id',
        'software_id'
    ];

    public function software()
    {
        return $this->belongsTo(Software::class, 'software_id');
    }

    public function scopeOrderSoftware($query, $order)
    {
        return $query->join('softwares', 'software_licenses.software_id', '=', 'softwares.id')->orderBy('softwares.name', $order);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'software_licenses_users', 'assigned_to', 'software_licenses_id');
    }

    public function allocatedSeats()
    {
        return $this->hasMany(LicensesUsers::class)->whereNull('deleted_at');
    }

    public function scopeByFilter($query, $filter)
    {
        return $query->where(function ($query) use ($filter) {
            foreach ($filter as $key => $search_val) {
                $fieldname = $key;
                if($fieldname == 'purchase_cost'){
                    $query->where('software_licenses.' . $fieldname, $search_val);
                }else{
                    $query->where('software_licenses.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }
            }
        });
    }

    public function advancedTextSearch(Builder $query, array $terms)
    {  
        foreach ($terms as $term) {
            $query = $query
                ->Where('software_licenses.seats', $term)
                ->orwhere('software_licenses.purchase_cost',  $term)
                ->orwhere('software_licenses.licenses', 'LIKE', '%' . $term . '%');
        }
        return $query;
    }

    public function availableForCheckout()
    {
        $allocatedSeats = $this->allocatedSeats()->count();
        if ($this->deleted != null || $allocatedSeats == $this->seats || $this->seats == 0) {
            return false;
        }
        return true;
    }
}
