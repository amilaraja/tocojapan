<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'rate_to_usd', 'is_active', 'sort_order', 'rates_updated_at'];

    protected $casts = [
        'rate_to_usd' => 'decimal:6',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'rates_updated_at' => 'datetime',
    ];

    public static function default(): self
    {
        return self::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'rate_to_usd' => 1, 'is_active' => true]);
    }
}
