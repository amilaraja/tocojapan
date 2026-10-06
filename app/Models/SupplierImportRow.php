<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierImportRow extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'applied' => 'bool',
        'old_price_fob' => 'decimal:2',
        'new_price_fob' => 'decimal:2',
    ];

    /** @return BelongsTo<SupplierImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(SupplierImport::class, 'supplier_import_id');
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
