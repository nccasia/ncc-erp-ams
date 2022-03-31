<?php

namespace App\Domains\Finfast\Models;

use App\Domains\Finfast\Models\Traits\Scope\FinfastSettingScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Announcement.
 */
class FinfastSetting extends Model
{
    use FinfastSettingScope;
    public static $EntryFilterKey = "EntryFilter";

    /**
     * @var string[]
     */
    protected $fillable = [];
    protected $casts = [
        'f_value' => 'array',
    ];

    public function setEntryFilter($value) {
        $this->f_key = self::$EntryFilterKey;
        $this->f_value = json_encode($value);
    }
}
