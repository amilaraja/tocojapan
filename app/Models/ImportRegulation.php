<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ImportRegulation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsToMany<Port, $this> */
    public function ports(): BelongsToMany
    {
        return $this->belongsToMany(Port::class);
    }
}
