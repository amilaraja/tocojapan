<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyType extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
