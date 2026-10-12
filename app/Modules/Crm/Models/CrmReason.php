<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/** Lost and close reasons (CRM-CAS-007). */
class CrmReason extends Model
{
    protected $table = 'crm_reasons';

    protected $guarded = [];

    protected $casts = ['is_active' => 'bool'];

    /** @return array<string, string> */
    public static function options(string $kind): array
    {
        return self::where('kind', $kind)->where('is_active', true)->orderBy('sort')->pluck('label', 'label')->all();
    }
}
