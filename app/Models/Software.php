<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Watson\Validating\ValidatingTrait;

class Software extends Depreciable
{
    use HasFactory;
    protected $presenter = \App\Presenters\SoftwarePresenter::class;
    use SoftDeletes;
    protected $injectUniqueIdentifier = true;
    use ValidatingTrait;

    public $timestamps = true;

    protected $guarded = 'id';
    protected $table = 'softwares';

    protected $rules = [
        'name' => 'required|string|min:3|max:255',
        'software_tag' => 'required|unique|string|min:3|max:255',
        'category_id' => 'required|exists:categories,id',
        'manufacturer_id' => 'required|exists:manufacturers,id',
        'version' => 'required|string|min:3|max:255',
        'notes' => 'string|nullable',
    ];

    protected $fillable = [
        'name',
        'software_tag',
        'version',
        'category_id',
        'manufacturer_id',
        'user_id',
        'notes',
        'created_at',
    ];
    use Searchable;

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function licenses()
    {
        return $this->hasMany(SoftwareLicenses::class);
    }

    /**
     * Get total liceneses of software
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function totalLicenses()
    {
        return $this->hasMany(SoftwareLicenses::class)->whereNull('deleted_at');
    }

    /**
     * Sort software by category
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderCategory($query, $order)
    {
        return $query->join('categories', 'softwares.category_id', '=', 'categories.id')->orderBy('categories.name', $order);
    }

    /**
     * Sort software by manufacturer
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderManufacturer($query, $order)
    {
        return $query->join('manufacturers', 'softwares.manufacturer_id', '=', 'manufacturers.id')->orderBy('manufacturers.name', $order);
    }

    /**
     * Sort software by checkout_count
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderCheckoutCount($query, $order)
    {
        return $query->with('licenses')->withSum('licenses', 'checkout_count')
            ->orderBy('licenses_sum_checkout_count', $order);
    }

    /**
     * Search software by manufacturer_id
     * 
     * @param  Builder $query
     * @param  int  $manufacturer_id
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeByManufacturer($query, $manufacturer_id)
    {
        return $query->join('manufacturers', 'softwares.manufacturer_id', '=', 'manufacturers.id')
            ->where('softwares.manufacturer_id', '=', $manufacturer_id);
    }

    /**
     * Filter softwares by manufacturer, category
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

                if ($fieldname == 'user') {
                    $query->whereHas('user', function ($query) use ($search_val) {
                        $query->where(function ($query) use ($search_val) {
                            $query->whereRaw('CONCAT(' . DB::getTablePrefix() . 'users.first_name," ",' . DB::getTablePrefix() . 'users.last_name) LIKE ?', ["%$search_val%"]);
                        });
                    });
                }

                if ($fieldname != 'category' && $fieldname != 'manufacturer' && $fieldname != 'user') {
                    $query->where('softwares.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }
            }
        });
    }

    /**
     * Search software by information of software
     * 
     * @param  Builder $query
     * @param  array  $terms
     * 
     * @return  \Illuminate\Database\Query\Builder
     */
    public function advancedTextSearch(Builder $query, array $terms)
    {
        $query = $query->leftJoin('users as softwares_user', function ($leftJoin) {
            $leftJoin->on('softwares_user.id', '=', 'softwares.user_id');
        });

        $query = $query->leftJoin('categories as softwares_category', function ($leftJoin) {
            $leftJoin->on('softwares_category.id', '=', 'softwares.category_id');
        });

        $query = $query->leftJoin('manufacturers as softwares_manufacturer', function ($leftJoin) {
            $leftJoin->on('softwares_manufacturer.id', '=', 'softwares.manufacturer_id');
        });

        foreach ($terms as $term) {
            $query = $query
                ->orWhere('softwares_user.first_name', 'LIKE', '%' . $term . '%')
                ->orWhere('softwares_user.last_name', 'LIKE', '%' . $term . '%')
                ->orWhere('softwares_user.username', 'LIKE', '%' . $term . '%')
                ->orWhereRaw('CONCAT(' . DB::getTablePrefix() . 'softwares_user.first_name," ",' . DB::getTablePrefix() . 'softwares_user.last_name) LIKE ?', ["%$term%"])
                ->orwhere('softwares_category.name', 'LIKE', '%' . $term . '%')
                ->orwhere('softwares_manufacturer.name', 'LIKE', '%' . $term . '%')
                ->orwhere('softwares.name', 'LIKE', '%' . $term . '%')
                ->orwhere('softwares.version', 'LIKE', '%' . $term . '%')
                ->orwhere('softwares.notes', 'LIKE', '%' . $term . '%')
                ->orwhere('softwares.software_tag', 'LIKE', '%' . $term . '%')
                ->orwhere('softwares.id', '=', $term);
        }
        return $query;
    }

    public function availableForCheckout()
    {
        return $this->licenses()->count() != 0 && !$this->deleted_at;
    }
}