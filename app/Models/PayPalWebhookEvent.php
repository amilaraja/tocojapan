<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inbound PayPal webhook delivery. Rows exist purely so a replayed
 * delivery can be recognised and skipped; they double as an audit trail
 * of everything PayPal told us about an order.
 */
class PayPalWebhookEvent extends Model
{
    protected $table = 'paypal_webhook_events';

    protected $fillable = ['event_id', 'event_type', 'order_id', 'payload', 'processed_at', 'note'];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
