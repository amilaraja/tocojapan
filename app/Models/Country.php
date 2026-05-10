<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<Port, $this> */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
