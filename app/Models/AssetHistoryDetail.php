<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Searchable;
use DB;

class AssetHistoryDetail extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'asset_history_details';
    protected $fillable = [
        'asset_histories_id',
        'asset_id'
    ];

    public function asset_history()
    {
        return $this->belongsTo(\App\Models\AssetHistory::class, 'asset_histories_id');
    }

    public function asset()
    {
        return $this->belongsTo(\App\Models\Asset::class, 'asset_id');
    }

    /**
     * -----------------------------------------------
     * BEGIN QUERY SCOPES
     * -----------------------------------------------
     **/

    public function advancedTextSearch(Builder $query, array $terms)
    {        
        $query = $query->leftJoin('users', 'users.id', '=', 'asset_histories.assigned_to');
     
        foreach ($terms as $term) {  
                $query = $query->where(function ($query) use ($term) {
                    $query->orwhere('users.first_name', 'LIKE', '%' . $term . '%')
                    ->orWhere('users.last_name', 'LIKE', '%' . $term . '%')
                    ->orWhere('users.username', 'LIKE', '%' . $term . '%')
                    ->orWhereRaw('CONCAT(' . DB::getTablePrefix() . 'users.first_name," ",' . DB::getTablePrefix() . 'users.last_name) LIKE ?', ["%$term%"]);
                });
        }

        return $query;
    }

    public function scopeInModelList($query, array $modelIdListing)
    {
        return $query->whereIn('assets.model_id', $modelIdListing);
    }

    public function scopeInCategory($query, $category_id)
    {
        return $query->join('models as category_models', 'assets.model_id', '=', 'category_models.id')
            ->join('categories', 'category_models.category_id', '=', 'categories.id')->where('category_models.category_id', '=', $category_id);
    }
}
