<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Projects extends Model
{
    use HasFactory;
    /**
 * The attributes that are mass assignable.
 *
 * @var array
 */
    protected $fillable = [
        'id',
        'name',
    ];
    public function asset()
    {
        return $this->hasOne(\App\Models\Asset::class, 'project_id');
    }
}
