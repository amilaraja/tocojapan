<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/** Email template with {placeholders} (CRM-ACT-002, NTF-002). */
class CrmEmailTemplate extends Model
{
    protected $table = 'crm_email_templates';

    protected $guarded = [];

    protected $casts = ['system' => 'bool'];

    public static function byKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }
}
