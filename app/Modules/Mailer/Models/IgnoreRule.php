<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;

class IgnoreRule extends Model
{
    protected $table = 'mailer_ignore_rules';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (IgnoreRule $rule): void {
            $rule->value = strtolower(trim((string) $rule->value));
            $rule->type = str_starts_with($rule->value, '@') ? 'domain' : 'address';
        });
    }
}
