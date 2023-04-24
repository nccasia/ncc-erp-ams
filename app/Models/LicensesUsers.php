<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Watson\Validating\ValidatingTrait;

class LicensesUsers extends Model
{
    use HasFactory, ValidatingTrait, Searchable;
    public $timestamps = true;
    protected $guarded = 'id';
    protected $table = 'software_licenses_users';

    protected $rules = [
        'software_licenses_id' => 'required|exists:software_licenses,id',
        'assigned_to' => 'required|exists:users,id',
        'notes' => 'string|nullable',
    ];

    protected $fillable = [
        'software_licenses_id',
        'assigned_to',
        'user_id',
        'deleted_at',
        'created_at',
        'checkout_at',
        'notes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function license()
    {
        return $this->belongsTo(SoftwareLicenses::class, 'software_licenses_id');
    }

    /**
     * Sort licenses by first name or last name of user
     *
     * @param Builder $query
     * @param  string  $order
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderAssigned($query, $order)
    {
        return $query->leftJoin('users', 'software_licenses_users.assigned_to', '=', 'users.id')
            ->orderBy('users.first_name', $order)
            ->orderBy('users.last_name', $order);
    }

     /**
     * Sort licenses by id of user
     *
     * @param Builder $query
     * @param  string  $order
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderByUserId($query, $order)
    {
        return $query->leftJoin('users', 'software_licenses_users.assigned_to', '=', 'users.id')
            ->orderBy('users.id', $order);
    }

    /**
     * Sort licenses by departments of user
     *
     * @param Builder $query
     * @param  string  $order
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderDepartment($query, $order)
    {
        return $query->leftJoin('users as license_seat_users', 'software_licenses_users.assigned_to', '=', 'license_seat_users.id')
            ->leftJoin('departments as license_user_dept', 'license_user_dept.id', '=', 'license_seat_users.department_id')
            ->orderBy('license_user_dept.name', $order);
    }

    /**
     * Sort licenses by location of user
     *
     * @param Builder $query
     * @param  string  $order
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderLocation($query, $order)
    {
        return $query->leftJoin('users as license_seat_users', 'software_licenses_users.assigned_to', '=', 'license_seat_users.id')
            ->leftJoin('departments as license_user_dept', 'license_user_dept.id', '=', 'license_seat_users.department_id')
            ->leftJoin('locations as department_location', 'license_user_dept.location_id', '=', 'department_location.id')
            ->orderBy('department_location.name', $order);
    }
}