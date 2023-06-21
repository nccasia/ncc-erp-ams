<?php

namespace App\Models;

use App\Events\CheckoutableCheckedOut;
use App\Exceptions\CheckoutNotAllowed;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Watson\Validating\ValidatingTrait;
use App\Presenters\Presentable;

class DigitalSignatures extends Model
{
    protected $presenter = \App\Presenters\AssetPresenter::class;
    use HasFactory;
    use SoftDeletes;
    use ValidatingTrait;
    use Searchable;
    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'digital_signatures';

    protected $rules = [
        'seri' => 'required|string|min:1|max:255|unique:digital_signatures,seri',
        'supplier_id' => 'required|exists:suppliers,id',
        'user_id' => 'nullable|exists:users,id',
        'category_id' => 'required|integer|exists:categories,id',
        'location_id'     => 'exists:locations,id|nullable',
        'warranty_months' => 'numeric|nullable|digits_between:0,240',
        'qty'               => 'required|integer|min:1',
        'assisgned_to' => 'nullable|exists:users,id',
        'purchase_date' => 'required|date',
        'purchase_cost' => 'required|numeric',
        'expiration_date' => 'required|date',
        'status_id' => 'nullable|numeric',
        'note' => 'nullable|string',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class, 'category_id')->where('category_type', '=', 'taxtoken');
    }

    public function location()
    {
        return $this->belongsTo(\App\Models\Location::class, 'location_id');
    }

    public function tokenStatus()
    {
        return $this->belongsTo(\App\Models\Statuslabel::class, 'status_id');
    }

    /**
     * Sort signature by user.
     *
     * @param Builder $query
     * @param string  $order
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderUser($query, $order)
    {
        return $query->join('users', 'users.id', '=', $this->table . '.user_id')
            ->orderBy('users.username', $order);
    }

    /**
     * Sort signature by assigned user.
     *
     * @param Builder $query
     * @param string  $order
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderAssignToUser($query, $order)
    {
        return $query->leftJoin('users', 'users.id', '=', $this->table . '.assigned_to')
            ->orderBy('users.username', $order);
    }

    /**
     * Sort signature by supplier.
     *
     * @param Builder $query
     * @param string  $order
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderSupplier($query, $order)
    {
        return $query->join('suppliers', 'suppliers.id', '=', $this->table . '.supplier_id')->select($this->table . '.*')
            ->orderBy('suppliers.name', $order);
    }

    /**
     * Sort signature by location.
     *
     * @param Builder $query
     * @param string  $order
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderLocation($query, $order)
    {
        return $query->join('locations', 'locations.id', '=', $this->table . '.location_id')->select($this->table . '.*')
            ->orderBy('locations.name', $order);
    }

    /**
     * Sort signature by category.
     *
     * @param Builder $query
     * @param string  $order
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeOrderCategory($query, $order)
    {
        return $query->join('locations', 'locations.id', '=', $this->table . '.location_id')->select($this->table . '.*')
            ->orderBy('locations.name', $order);
    }

    /**
     * Filter signature by supplier, assigned user.
     *
     * @param Builder $query
     * @param array   $filter
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFilter($query, $filter)
    {
        $query = $query->where(function (Builder $query) use ($filter) {
            foreach ($filter as $key => $search_val) {
                $fieldname = $key;
                $customField = [
                    'supplier',
                    'assignedTo',
                ];

                if (!in_array($fieldname, $customField)) {
                    $query->where($this->table . '.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }

                if ($fieldname == 'supplier') {
                    $query->whereHas('supplier', function (Builder $query) use ($search_val) {
                        $query->where('suppliers.name', 'LIKE', '%' . $search_val . '%');
                    });
                }

                if ($fieldname == 'assignedTo') {
                    $query->whereHas('assignedUser', function (Builder $query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->orWhere('users.username', 'LIKE', ["%$search_val%"]);
                            $query->orWhereRaw('CONCAT(' . DB::getTablePrefix() . 'users.first_name," ",' . DB::getTablePrefix() . 'users.last_name) LIKE ?', ["%$search_val%"]);
                        });
                    });
                }
            }
        });

        return $query;
    }

    public function scopeInCategory($query, $category_id)
    {
        return $query->join('categories', $this->table . '.category_id', '=', 'categories.id')->where($this->table . '.category_id', '=', $category_id);
    }

    public function scopeInSupplier($query, $supplier_id)
    {
        return $query->join('suppliers', $this->table . '.supplier_id', '=', 'suppliers.id')->where($this->table . '.supplier_id', '=', $supplier_id);
    }

    /**
     * Filter signature by supplier, assigned user.
     *
     * @param Builder $query
     * @param array $filter
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function advancedTextSearch(Builder $query, array $terms)
    {
        // assign user
        $query = $query->leftJoin('users as asssigned_users', 'asssigned_users.id', '=', $this->table . '.assigned_to');
        foreach ($terms as $term) {
            $query = $query
                ->orWhere('asssigned_users.first_name', 'LIKE', '%' . $term . '%')
                ->orWhere('asssigned_users.last_name', 'LIKE', '%' . $term . '%')
                ->orWhere('asssigned_users.username', 'LIKE', '%' . $term . '%')
                ->orWhereRaw('CONCAT(' . DB::getTablePrefix() . 'asssigned_users.first_name," ",' . DB::getTablePrefix() . 'asssigned_users.last_name) LIKE ?', ["%$term%"]);
        }

        // assigned suppliers
        $query = $query->leftJoin('suppliers as suppliers_signature', 'suppliers_signature.id', '=', 'digital_signatures.supplier_id');
        foreach ($terms as $term) {
            $query = $query->orWhere('suppliers_signature.name', 'LIKE', '%' . $term . '%');
        }

        // assigned digital signatures
        foreach ($terms as $term) {
            $query = $query->orWhere($this->table . '.seri', 'LIKE', '%' . $term . '%');
            $query = $query->orWhere($this->table . '.note', 'LIKE', '%' . $term . '%');
        }

        return $query;
    }

    public function availableForCheckout()
    {
        return $this->checkIsAdmin() &&
            !$this->deleted_at &&
            !$this->assigned_to &&
            !$this->withdraw_from &&
            $this->assigned_status === config('enum.assigned_status.DEFAULT') &&
            $this->status_id === config('enum.status_tax_token.NOT_ACTIVE');
    }

    public function availableForCheckin()
    {
        return $this->checkIsAdmin() &&
            !$this->deleted_at &&
            $this->assigned_to &&
            in_array($this->assigned_status, [config('enum.assigned_status.ACCEPT'), config('enum.assigned_status.REJECT')]) &&
            $this->status_id === config('enum.status_tax_token.ASSIGN');
    }

    /**
     * Check current User is admin
     *
     * @return bool
     */
    public function checkIsAdmin() {
        $user = Auth::user();
        return $user->isAdmin();
    }

    /**
     * Get the target this asset is checked out to
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function assignedTo()
    {
        return $this->morphTo('assigned', 'assigned_type', 'assigned_to')->withTrashed();
    }

    public function checkOut($target, $checkout_date, $note, $signature_name, $location, $status)
    {
        if (!$target) {
            return false;
        }

        if ($location != null) {
            $this->location_id = $location;
        } else {
            if (isset($target->location)) {
                $this->location_id = $target->location->id;
            }
            if ($target instanceof Location) {
                $this->location_id = $target->id;
            }
        }
        // $this->assigned_to = $target;
        $this->assignedTo()->associate($target);
        $this->last_checkout = $checkout_date;

        if ($signature_name != null) {
            $this->name = $signature_name;
        }

        if ($status !== null) {
            $this->assigned_status = $status;
        }

        if ($this->save()) {
            return true;
        }

        return false;
    }


    public function checkIn($target, $checkout_date, $note, $signature_name, $status)
    {
        if (!$target) {
            return false;
        }
        $this->withdraw_from = $this->assigned_to;

        if ($signature_name != null) {
            $this->name = $signature_name;
        }

        if ($status !== null) {
            $this->assigned_status = $status;
        }

        if ($this->save()) {
            return true;
        }

        return false;
    }
}