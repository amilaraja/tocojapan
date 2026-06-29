<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleOption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    /** "ASK" options (no price set) — buyer ticks for follow-up. */
    public function isAsk(): bool
    {
        return $this->price === null;
    }
}
