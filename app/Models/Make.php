<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Make extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<VehicleModel, $this> */
    public function vehicleModels(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
