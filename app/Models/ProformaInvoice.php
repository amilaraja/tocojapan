<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaInvoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'issued_on' => 'date',
        'expires_on' => 'date',
        'price_fob' => 'decimal:2',
        'discount' => 'decimal:2',
        'insurance' => 'decimal:2',
        'freight' => 'decimal:2',
        'total_cif' => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Port, $this> */
    public function destPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'dest_port_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_on->endOfDay()->isPast();
    }

    public function fileName(): string
    {
        return 'Proforma-Invoice-'.preg_replace('/[^A-Za-z0-9-]/', '', $this->invoice_no).'.pdf';
    }
}
