<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Watson\Validating\ValidatingTrait;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tool extends Model
{
    use HasFactory, Searchable, ValidatingTrait, SoftDeletes;

    public $timestamps = true;
    protected $guarded = 'id';
    protected $table = 'tools';
    protected $injectUniqueIdentifier = true;

    protected $rules = [
        'name' => 'required|unique|string|min:3|max:255',
        'category_id' => 'required|exists:categories,id',
        'purchase_cost' => 'required',
        'purchase_date' => 'required',
        'manufacturer_id' => 'required|exists:manufacturers,id',
        'version' => 'required|string|min:3|max:255',
        'notes' => 'string',
    ];

    protected $fillable = [
        'name',
        'version',
        'category_id',
        'manufacturer_id',
        'user_id',
        'purchase_cost',
        'purchase_date',
        'notes',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tools_users', 'tool_id', 'assigned_to');
    }

    public function toolsUsers()
    {
        return $this->hasMany(ToolUser::class);
    }

    /**
     * Sort tools by category
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderCategory($query, $order)
    {
        return $query->join('categories', 'tools.category_id', '=', 'categories.id')->orderBy('categories.name', $order);
    }

    /**
     * Sort tools by manufacturer
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderManufacturer($query, $order)
    {
        return $query->join('manufacturers', 'tools.manufacturer_id', '=', 'manufacturers.id')
            ->orderBy('manufacturers.name', $order);
    }

    /**
     * Sort tools by checkout_count
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderCheckoutCount($query, $order)
    {
        return $query->with('licenses')->withSum('licenses', 'checkout_count')
            ->orderBy('licenses_sum_checkout_count', $order);
    }

    /**
     * Search tools by manufacturer_id
     * 
     * @param  Builder $query
     * @param  int  $manufacturer_id
     * 
     * @return  \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByManufacturer($query, $manufacturer_id)
    {
        return $query->join('manufacturers', 'tools.manufacturer_id', '=', 'manufacturers.id')
            ->where('tools.manufacturer_id', '=', $manufacturer_id);
    }

    /**
     * Filter tools by manufacturer, category
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
                if ($fieldname == 'manufacturer') {
                    $query->whereHas('manufacturer', function ($query) use ($search_val) {
                        $query->where('manufacturers.name', 'LIKE', '%' . $search_val . '%');
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

        $query = $query->leftJoin('manufacturers as tools_manufacturer', function ($leftJoin) {
            $leftJoin->on('tools_manufacturer.id', '=', 'tools.manufacturer_id');
        });

        foreach ($terms as $term) {
            $query = $query
                ->where('tools_category.name', 'LIKE', '%' . $term . '%')
                ->orwhere('tools_manufacturer.name', 'LIKE', '%' . $term . '%')
                ->orwhere('tools.name', 'LIKE', '%' . $term . '%')
                ->orwhere('tools.version', 'LIKE', '%' . $term . '%')
                ->orwhere('tools.id', '=', $term);
        }
        return $query;
    }

    /**
     * Check tool available for checkout
     * 
     * @return  boolean
     */
    public function availableForCheckout()
    {
        return !$this->deleted_at;
    }

    /**
     * Check tool available for checkin 
     * 
     * @param  int $assigned_user
     * @return  boolean
     */
    public function availableForCheckin($assigned_user)
    {
        $tool_user = $this->toolsUsers()
            ->whereNull('checkin_at')
            ->whereNotNull('checkout_at')
            ->where('tool_id', $this->id)
            ->where('assigned_to', $assigned_user)
            ->first();

        return $tool_user && !$this->deleted_at;
    }
}