<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Watson\Validating\ValidatingTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToolUser extends Model
{
    use HasFactory, ValidatingTrait, Searchable, SoftDeletes;
    
    public $timestamps = true;
    protected $guarded = 'id';
    protected $table = 'tools_users';

    protected $rules = [
        'tool_id' => 'required|exists:tools,id',
        'assigned_to' => 'required|exists:users,id',
        'checkout_at' => 'nullable|date',
        'checkin_at' => 'nullable|date',
        'notes' => 'string|nullable',
    ];

    protected $fillable = [
        'tool_id',
        'assigned_to',
        'deleted_at',
        'created_at',
        'checkout_at',
        'checkin_at',
        'notes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function tool()
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * Sort tools by category
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderCategory($query, $order)
    {
        return $query->join('tools', 'tools_users.tool_id', 'tools.id')
            ->join('categories', 'tools.category_id', 'categories.id')
            ->orderBy('categories.name', $order);
    }

    /**
     * Sort tools by manufacturer
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderManufacturer($query, $order)
    {
        return $query->join('tools', 'tools_users.tool_id', 'tools.id')
            ->join('manufacturers', 'tools.manufacturer_id', 'manufacturers.id')
            ->orderBy('manufacturers.name', $order);
    }

    /**
     * Sort tools by assigned user name
     * 
     * @param  Builder $query
     * @param  string $order
     * 
     * @return  \Illuminate\Database\Query\Builder 
     */
    public function scopeOrderAssingedTo($query, $order)
    {
        return $query->join('users', 'tools_users.assigned_to', 'users.id')
            ->orderBy('users.username', $order);
    }
    
    /**
     * Search tools by information of tools
     * 
     * @param  Builder $query
     * @param  array  $terms
     * 
     * @return  \Illuminate\Database\Query\Builder
     */
    public function advancedTextSearch($query, array $terms)
    {
        $query= $query->join('tools as tool', 'tools_users.tool_id', 'tool.id');
        $query = $query->leftJoin('users as tool_users', function ($leftJoin) {
            $leftJoin->on('tool_users.id', '=', 'tools_users.assigned_to');
        });

        $query = $query->leftJoin('categories as tools_category', function ($leftJoin) {
            $leftJoin->on('tools_category.id', '=', 'tool.category_id');
        });

        $query = $query->leftJoin('manufacturers as tools_manufacturer', function ($leftJoin) {
            $leftJoin->on('tools_manufacturer.id', '=', 'tool.manufacturer_id');
        });
        foreach ($terms as $term) {
            $query = $query
                ->where('tools_category.name', 'LIKE', '%' . $term . '%')
                ->orwhere('tools_manufacturer.name', 'LIKE', '%' . $term . '%')
                ->orwhere('tool.name', 'LIKE', '%' . $term . '%')
                ->orwhere('tool.version', 'LIKE', '%' . $term . '%')
                ->orwhere('tool.notes', 'LIKE', '%' . $term . '%')
                ->orwhere('tool.id', '=', $term)
                ->orwhere('tool_users.username', 'LIKE','%' . $term . '%');
        }
        return $query;
    }

    /**
     * Filter tools by manufacturer, category
     * 
     * @param  Builder $query
     * @param  array  $filter
     * 
     * @return \Illuminate\Database\Query\Builder 
     */
    public function scopeByFilter($query, $filter)
    {
        return $query->join('tools as tool', 'tools_users.tool_id', 'tool.id')->where(function ($query) use ($filter) {
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
                    $query->where('tool.' . $fieldname, 'LIKE', '%' . $search_val . '%');
                }
            }
        });
    }
}