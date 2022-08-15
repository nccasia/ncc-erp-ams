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
        $query = $query->leftJoin('locations', 'locations.id', '=', 'assets.rtd_location_id')
            ->leftJoin('status_labels', 'status_labels.id', '=', 'assets.status_id')
            ->leftJoin('models', 'models.id', '=', 'assets.model_id')
            ->leftJoin('categories', 'categories.id', '=', 'models.category_id')
            ->leftJoin('users', 'users.id', '=', 'asset_histories.assigned_to');

        foreach ($terms as $term) {
            $query = $query->where(function ($query) use ($term) {
                $query->orwhere('assets.name', 'LIKE', '%' . $term . '%')
                    ->orWhere('assets.asset_tag', 'LIKE', '%' . $term . '%')
                    ->orWhere('categories.name', 'LIKE', '%' . $term . '%')
                    ->orWhere('locations.name', 'LIKE', '%' . $term . '%')
                    ->orWhere('status_labels.name', 'LIKE', '%' . $term . '%')
                    ->orwhere('users.first_name', 'LIKE', '%' . $term . '%')
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

    // scope for sort
    public function scopeOrderIds($query, $order)
    {
        return $query->orderBy('asset_history_details.id', $order);
    }

    public function scopeOrderCreatedAt($query, $order)
    {
        return $query->orderBy('asset_history_details.created_at', $order);
    }

    public function scopeOrderName($query, $order)
    {
        return $query->orderBy('assets.name', $order);
    }

    public function scopeOrderAssetTag($query, $order)
    {
        return $query->orderBy('assets.asset_tag', $order);
    }

    public function scopeOrderRtdLocation($query, $order)
    {
        return $query->orderBy('assets.rtd_location_id', $order);
    }

    public function scopeOrderCategory($query, $order)
    {
        return $query->join('models', 'assets.model_id', '=', 'models.id')
            ->join('categories', 'models.category_id', '=', 'categories.id')
            ->orderBy('categories.id', $order);
    }

    public function scopeOrderAssignedTo($query, $order)
    {
        return $query->leftJoin('users as users_sort', 'asset_histories.assigned_to', '=', 'users_sort.id')->orderBy('users_sort.first_name', $order)->orderBy('users_sort.last_name', $order);
    }
}
