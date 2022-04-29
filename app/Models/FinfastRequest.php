<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinfastRequest extends Model
{
    use HasFactory;
    use Searchable;


    protected $fillable = [
        'name',
        'status',
        'branch_id',
        'supplier_id',
        'entry_id',
        'note'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    public function finfast_request_assets(){
        return $this->hasMany(FinfastRequestAsset::class);
    }


    /**
     * -----------------------------------------------
     * BEGIN QUERY SCOPES
     * -----------------------------------------------
     **/

    /**
     * Run additional, advanced searches.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  array  $terms The search terms
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function advancedTextSearch(Builder $query, array $terms)
    {

        foreach ($terms as $term) {
            $query = $query->orWhere('name', 'LIKE', '%'.$term.'%')
                            ->orWhere('status', 'LIKE', '%'.$term.'%')
                            ->orWhere('note', 'LIKE', '%'.$term.'%');

        }
        return $query;
    }


}
