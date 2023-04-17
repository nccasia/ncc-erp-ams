<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Watson\Validating\ValidatingTrait;

class LicensesUsers extends Model
{
    use HasFactory, ValidatingTrait, Searchable;
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
        'checkout_at',
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

                if ($fieldname == 'assigned_user') {
                    $query->whereHas('user', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->where('users.first_name', 'LIKE', '%' . $search_val . '%')
                                ->orWhere('users.last_name', 'LIKE', '%' . $search_val . '%')
                                ->orWhereRaw('CONCAT(' . DB::getTablePrefix() . 'users.first_name," ",' . DB::getTablePrefix() . 'users.last_name) LIKE ?', ["%$search_val%"]);
                        });
                    });
                }

                if ($fieldname == 'license_active') {
                    $query->whereHas('license', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->where('software_licenses.licenses', 'LIKE', '%' . $search_val . '%');
                        });
                    });
                }

                if ($fieldname == 'department') {
                    $query->whereHas('user.department', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->where('departments.name', 'LIKE', '%' . $search_val . '%')
                                ->orWhere('departments.id',  $search_val);
                        });
                    });
                }

                if ($fieldname == 'location') {
                    $query->whereHas('user.department.location', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->where('locations.name', 'LIKE', '%' . $search_val . '%')
                                ->orWhere('locations.id',  $search_val);
                        });
                    });
                }

                if ($fieldname != 'assigned_user' &&  $fieldname != 'license_active' 
                    && $fieldname != 'department' && $fieldname != 'location') {
                    $query->where('software_licenses_users.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }
            }
        });
    }

    public function advancedTextSearch(Builder $query, array $terms){
        $search = $terms[0];
        $query = $query->leftJoin('users as license_users', 'license_users.id', '=', 'software_licenses_users.assigned_to')
                    ->leftJoin('departments', 'license_users.department_id', '=', 'departments.id')
                    ->leftJoin('locations', 'departments.location_id', '=', 'locations.id')
                    ->whereRaw('CONCAT(' . 'license_users.first_name," ",' . 'license_users.last_name) LIKE ?', ["%$search%"])
                    ->orWhere('departments.name',  'LIKE', '%' . $search . '%')
                    ->orWhere('locations.name',  'LIKE', '%' . $search . '%');
        return $query;
    }

    public function scopeOrderAssigned($query, $order)
    {
        return $query->leftJoin('users', 'software_licenses_users.assigned_to', '=', 'users.id')
        ->orderBy('users.first_name', $order)
        ->orderBy('users.last_name', $order);
    }

    public function scopeOrderDepartment($query, $order)
    {
        return $query->leftJoin('users as license_seat_users', 'software_licenses_users.assigned_to', '=', 'license_seat_users.id')
            ->leftJoin('departments as license_user_dept', 'license_user_dept.id', '=', 'license_seat_users.department_id')
            ->orderBy('license_user_dept.name', $order);
    }

    public function scopeOrderLocation($query, $order)
    {
        return $query->leftJoin('users as license_seat_users', 'software_licenses_users.assigned_to', '=', 'license_seat_users.id')
            ->leftJoin('departments as license_user_dept', 'license_user_dept.id', '=', 'license_seat_users.department_id')
            ->leftJoin('locations as department_location', 'license_user_dept.location_id', '=', 'department_location.id')
            ->orderBy('department_location.name', $order);
    }
}
