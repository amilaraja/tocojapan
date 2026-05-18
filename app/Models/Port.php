<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Port extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rate_per_m3' => 'decimal:2',
        'insurance_pct' => 'decimal:4',
        'shipping_modes' => 'array',
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
