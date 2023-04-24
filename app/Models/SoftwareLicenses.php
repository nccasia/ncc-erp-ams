<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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
        'purchase_date' => 'date',
        'expiration_date' => 'date',
        'seats' => 'integer',
        'checkout_count' => 'integer',
        'software_id' => 'integer',
    ];
    protected $fillable = [
        'expiration_date',
        'purchase_cost',
        'purchase_date',
        'seats',
        'user_id',
        'checkout_count',
        'software_id'
    ];

    public function software()
    {
        return $this->belongsTo(Software::class, 'software_id');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'software_licenses_users', 'software_licenses_id', 'assigned_to');
    }

    public function allocatedSeats()
    {
        return $this->hasMany(LicensesUsers::class)->whereNull('deleted_at');
    }

    /**
     * Sort licenses by id name Software
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderSoftware($query, $order)
    {
        return $query->join('softwares', 'software_licenses.software_id', '=', 'softwares.id')->orderBy('softwares.name', $order);
    }

    /**
     * Sort licenses by name of manufacturers
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderManufacturer($query, $order)
    {
        return $query->leftJoin('softwares', 'software_licenses.software_id', '=', 'softwares.id')
                ->leftJoin('manufacturers', 'softwares.manufacturer_id', '=', 'manufacturers.id')
                ->orderBy('manufacturers.name', $order);
    }

    /**
     * Sort licenses by name of categories
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderCategories($query, $order)
    {
        return $query->leftJoin('softwares', 'software_licenses.software_id', '=', 'softwares.id')
                ->leftJoin('categories', 'softwares.category_id', '=', 'categories.id')
                ->orderBy('categories.name', $order);
    }

    /**
     * Filter licenses follow name, seats, purchase_cost, licenses
     * 
     * @param  Builder $query
     * @param  array $filter
     * 
     * @return  \Illuminate\Database\Eloquent\Builder 
     */
    public function scopeByFilter($query, $filter)
    {
        return $query->where(function ($query) use ($filter) {
            foreach ($filter as $key => $search_val) {
                $fieldname = $key;
                if ($fieldname == 'purchase_cost') {
                    $query->where('software_licenses.' . $fieldname, $search_val);
                } else {
                    $query->where('software_licenses.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }
            }
        });
    }

    /**
     * Search licenses follow name, seats, purchase_cost, licenses
     * 
     * @param  Builder $query
     * @param  array $terms
     * 
     * @return  \Illuminate\Database\Query\Builder
     */
    public function advancedTextSearch(Builder $query, array $terms)
    {
        $query = $query->leftJoin('softwares as softwares', function ($leftJoin) {
            $leftJoin->on('softwares.id', '=', 'software_licenses.software_id');
        });

        foreach ($terms as $term) {
            $query = $query
                ->Where('software_licenses.seats', $term)
                ->orwhere('software_licenses.purchase_cost', $term)
                ->orwhere('softwares.name', 'LIKE', '%' . $term . '%')
                ->orwhere('software_licenses.licenses', 'LIKE', '%' . $term . '%');
        }
        return $query;
    }

    /**
     * Check license available for checkout
     * 
     * @return bool
     */
    public function availableForCheckout()
    {
        $now = Carbon::now();
        $expirationDate = Carbon::parse($this->expiration_date);

        return $this->checkout_count < $this->seats && 
            $this->seats != 0 && 
            !$this->deleted_at && 
            !($now->diffInDays($expirationDate, false) < 0);
    }

    /**
     * Get first license of software available for checkout
     *
     * @param  int $softwareId
     * @param  int $assigned_user
     * 
     * @return SoftwareLicenses 
     */
    public function getFirstLicenseAvailableForCheckout($softwareId, $assigned_user)
    {
        return $this->leftJoin('software_licenses_users', 'software_licenses.id', '=', 'software_licenses_users.software_licenses_id')
            ->select(
                'software_licenses.id',
                'software_licenses.checkout_count',
                'software_licenses.seats',
                'software_licenses.licenses',
                DB::raw('count(software_licenses_users.id) as allocatedSeat')
            )
            ->whereNull('software_licenses.deleted_at')
            ->where('software_id', '=', $softwareId)
            ->where('seats', '>', config('enum.seats.MIN'))
            ->where('expiration_date', '>', Carbon::now())
            ->groupBy('software_licenses_users.software_licenses_id')
            ->havingRaw('software_licenses.seats > allocatedSeat')
            ->whereNotExists(function ($query) use ($assigned_user) {
                $query->select(DB::raw(1))
                    ->from('software_licenses_users as license_checkout')
                    ->whereRaw('license_checkout.software_licenses_id = software_licenses.id')
                    ->whereRaw('license_checkout.assigned_to = ?', [$assigned_user]);
            })
            ->orderBy('id')
            ->first();
    }
}