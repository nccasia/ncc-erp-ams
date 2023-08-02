<?php

namespace App\Models;

use App\Events\CheckoutableCheckedIn;
use App\Events\CheckoutableCheckedOut;
use App\Models\Traits\Acceptable;
use App\Presenters\Presentable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Watson\Validating\ValidatingTrait;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tool extends Model
{
    use HasFactory, Searchable, ValidatingTrait, SoftDeletes, Loggable, Acceptable, Presentable;

    public $timestamps = true;
    protected $guarded = 'id';
    protected $table = 'tools';
    protected $injectUniqueIdentifier = true;

    protected $rules = [
        'name' => 'required|unique|string|min:3|max:255',
        'supplier_id' => 'required|exists:suppliers,id',
        'user_id' => 'nullable|exists:users,id',
        'category_id' => 'required|integer|exists:categories,id',
        'location_id'     => 'exists:locations,id|nullable',
        'qty'               => 'required|integer|min:1',
        'assisgned_to' => 'nullable|exists:users,id',
        'purchase_date' => 'required|date',
        'purchase_cost' => 'required|numeric|min:1',
        'expiration_date' => 'required|date|after:purchase_date',
        'status_id' => 'nullable|numeric',
        'notes' => 'nullable|string',
    ];

    protected $fillable = [
        'name',
        'category_id',
        'supplier_id',
        'user_id',
        'purchase_cost',
        'purchase_date',
        'notes',
        'assisgned_to',
        'qty',
        'location_id',
        'status_id',
        'expiration_date'
    ];

    protected $searchableAttributes = [
        'name',
        'purchase_cost',
        'purchase_date',
        'expiration_date',
        'notes',
        'qty',
    ];

    protected $searchableRelations = [
        'assetstatus'        => ['name'],
        'supplier'           => ['name'],
        'location'           => ['name'],
        'category'           => ['name'],
        'assignedUser'       => ['username']
    ];

    public function assetstatus()
    {
        return $this->belongsTo(\App\Models\Statuslabel::class, 'status_id');
    }

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
        return $this->belongsTo(Category::class, 'category_id')->where('category_type', '=', 'tool');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function tokenStatus()
    {
        return $this->belongsTo(Statuslabel::class, 'status_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tools_users', 'tool_id', 'assigned_to');
    }

    public function toolsUsers()
    {
        return $this->hasMany(ToolUser::class);
    }

    public function assignedTo()
    {
        return $this->morphTo('assigned', 'assigned_type', 'assigned_to')->withTrashed();
    }

    public function getRequireAcceptance()
    {
        return $this->category->require_acceptance;
    }

    public function getCheckinEmail()
    {
        return $this->category->checkin_email;
    }

    public function getEula()
    {
        $Parsedown = new \Parsedown();

        if ($this->category->eula_text) {
            return $Parsedown->text(e($this->category->eula_text));
        } elseif ((Setting::getSettings()->default_eula_text) && ($this->category->use_default_eula == '1')) {
            return $Parsedown->text(e(Setting::getSettings()->default_eula_text));
        }

        return null;
    }

    public function getImageUrl()
    {
        if ($this->image) {
            return Storage::disk('public')->url(app('accessories_upload_path') . $this->image);
        }
        return false;
    }

    public function getDisplayNameAttribute()
    {
        return $this->name;
    }

    public function scopeByStatusId($query, $id)
    {
        return $query->where('status_id', $id);
    }

    public function scopeOrderUser($query, $order)
    {
        return $query->join('users', 'users.id', '=', $this->table . '.user_id')
            ->orderBy('users.username', $order);
    }

    public function scopeOrderAssignToUser($query, $order)
    {
        return $query->leftJoin('users', 'users.id', '=', $this->table . '.assigned_to')
            ->orderBy('users.username', $order);
    }

    public function scopeOrderCategory($query, $order)
    {
        return $query->join('categories', 'tools.category_id', '=', 'categories.id')->orderBy('categories.name', $order);
    }

    public function scopeOrderSupplier($query, $order)
    {
        return $query->join('suppliers', 'tools.supplier_id', '=', 'suppliers.id')
            ->orderBy('suppliers.name', $order);
    }

    public function scopeOrderLocation($query, $order)
    {
        return $query->join('locations', 'locations.id', '=', $this->table . '.location_id')->select($this->table . '.*')
            ->orderBy('locations.name', $order);
    }

    public function scopeOrderCheckoutCount($query, $order)
    {
        return $query->with('licenses')->withSum('licenses', 'checkout_count')
            ->orderBy('licenses_sum_checkout_count', $order);
    }

    public function scopeBySupplier($query, $supplier_id)
    {
        return $query->join('suppliers', 'tools.supplier_id', '=', 'suppliers.id')
            ->where('tools.supplier_id', '=', $supplier_id);
    }

    public function scopeInCategory($query, $category_id)
    {
        return $query->join('categories', $this->table . '.category_id', '=', 'categories.id')->where($this->table . '.category_id', '=', $category_id);
    }

    public function scopeInSupplier($query, $supplier_id)
    {
        return $query->join('suppliers', $this->table . '.supplier_id', '=', 'suppliers.id')->where($this->table . '.supplier_id', '=', $supplier_id);
    }

    public function scopeInAssignedStatus($query, $assigned_status)
    {
        $data = $query;
        if (is_array($assigned_status)) {
            $data = $data->whereIn('assigned_status', $assigned_status);
        } else {
            $data = $data->where('assigned_status', '=', $assigned_status);
        }
        return $data;
    }

    public function scopeInStatus($query, $status)
    {
        $data = $query;
        if (is_array($status)) {
            $data = $data->whereIn('status_id', $status);
        } else {
            $data = $data->where('status_id', '=', $status);
        }
        return $data;
    }

    /**
     * Filter tools by supplier, category
     * 
     * @param  Builder $query
     * @param  array  $filter
     * 
     * @return  \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFilter($query, $filter)
    {
        return $query->where(function ($query) use ($filter) {
            foreach ($filter as $key => $search_val) {
                $fieldname = $key;
                if ($fieldname == 'supplier') {
                    $query->whereHas('supplier', function ($query) use ($search_val) {
                        $query->where('suppliers.name', 'LIKE', '%' . $search_val . '%');
                    });
                }

                if ($fieldname == 'category') {
                    $query->whereHas('category', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->where('categories.name', 'LIKE', '%' . $search_val . '%');
                        });
                    });
                }

                if ($fieldname != 'category' && $fieldname != 'manufacturer') {
                    $query->where('tools.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }
            }
        });
    }

    /**
     * Search tools by information of tools
     * 
     * @param  Builder $query
     * @param  array  $terms
     * 
     * @return  \Illuminate\Database\Eloquent\Builder
     */
    public function advancedTextSearch(Builder $query, array $terms)
    {
        $query = $query->leftJoin('categories as tools_category', function ($leftJoin) {
            $leftJoin->on('tools_category.id', '=', 'tools.category_id');
        });

        $query = $query->leftJoin('suppliers', function ($leftJoin) {
            $leftJoin->on('suppliers.id', '=', 'tools.supplier_id');
        });

        $query = $query->leftJoin('users', function ($leftJoin) {
            $leftJoin->on('users.id', '=', 'tools.assigned_to');
        });

        foreach ($terms as $term) {
            $query = $query
                ->where('tools_category.name', 'LIKE', '%' . $term . '%')
                ->orwhere('suppliers.name', 'LIKE', '%' . $term . '%')
                ->orwhere('users.username', 'LIKE', '%' . $term . '%')
                ->orwhere('tools.name', 'LIKE', '%' . $term . '%')
                ->orwhere('tools.id', '=', $term);
        }
        return $query;
    }

    public function checkIsAdmin()
    {
        $user = Auth::user();
        return $user->isAdmin();
    }

    public function checkOut($target, $checkout_date, $tool_name, $status, $note)
    {
        if (!$target) {
            return false;
        }

        $this->assignedTo()->associate($target);
        $this->last_checkout = $checkout_date;

        if ($tool_name != null) {
            $this->name = $tool_name;
        }

        if ($status !== null) {
            $this->assigned_status = $status;
        }

        if ($this->save()) {
            event(new CheckoutableCheckedOut($this, $target, Auth::user(), $note));
            return true;
        }

        return false;
    }

    public function checkIn($target, $checkout_date, $tool_name, $status, $note)
    {
        if (!$target) {
            return false;
        }
        $this->withdraw_from = $this->assigned_to;

        if ($tool_name != null) {
            $this->name = $tool_name;
        }

        if ($status !== null) {
            $this->assigned_status = $status;
        }

        if ($this->save()) {
            event(new CheckoutableCheckedIn($this, $target, Auth::user(), $note));
            return true;
        }

        return false;
    }

    /**
     * Check tool available for checkout
     * 
     * @return  boolean
     */
    public function availableForCheckout()
    {
        return $this->checkIsAdmin() &&
            !$this->deleted_at &&
            !$this->assigned_to &&
            !$this->withdraw_from &&
            $this->assigned_status === config('enum.assigned_status.DEFAULT') &&
            $this->status_id === config('enum.status_id.READY_TO_DEPLOY');
    }

    /**
     * Check tool available for checkin 
     * 
     * @param  int $assigned_user
     * @return  boolean
     */
    public function availableForCheckin()
    {
        return $this->checkIsAdmin() &&
            !$this->deleted_at &&
            $this->assigned_to &&
            in_array($this->assigned_status, [config('enum.assigned_status.ACCEPT'), config('enum.assigned_status.REJECT')]) &&
            $this->status_id === config('enum.status_id.ASSIGN');
    }
}
