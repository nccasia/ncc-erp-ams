<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Watson\Validating\ValidatingTrait;

class LicensesUsers extends Model
{
    use HasFactory, ValidatingTrait;
    public $timestamps = true;
    protected $guarded = 'id';
    protected $table = 'software_licenses_users';

    protected $rules =[
        'software_licenses_id' => 'required|exists:software_licenses,id',
        'assigned_to' => 'required|exists:users,id'
    ];

    protected $fillable = [
        'software_licenses_id',
        'assigned_to',
        'user_id',
        'deleted_at',
        'created_at',
    ];

    public function softwareLicenses(){
        return $this->belongsTo(SoftwareLicenses::class, 'software_licenses_id');
    }

    public function user(){
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function license()
    {
        return $this->belongsTo(SoftwareLicenses::class, 'software_licenses_id');
    }

    public function scopeByFilter($query, $filter){
        return $query->where(function ($query) use ($filter) {
            foreach ($filter as $key => $search_val) {
                $fieldname = $key;

                if ($fieldname == 'assignedTo') {
                    $query->whereHas('user', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->where('users.first_name', 'LIKE', '%' . $search_val . '%')
                                ->orWhere('users.last_name', 'LIKE', '%' . $search_val . '%')
                                ->orWhereRaw('CONCAT(' . DB::getTablePrefix() . 'users.first_name," ",' . DB::getTablePrefix() . 'users.last_name) LIKE ?', ["%$search_val%"]);
                        });
                    });
                }

                if ($fieldname == 'license') {
                    $query->whereHas('license', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->where('software_licenses.licenses', 'LIKE', '%' . $search_val . '%');
                        });
                    });
                }

                if ($fieldname != 'assignedTo' &&  $fieldname != 'license') {
                    $query->where('software_licenses_users.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }
            }
        });
    }

    public function advancedTextSearch(Builder $query, array $terms){
        
    }
}
