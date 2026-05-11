<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_no', 'user_id', 'vehicle_id', 'amount_usd', 'currency', 'status',
        'payment_provider', 'paypal_order_id', 'paypal_capture_id', 'payment_payload',
        'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'admin_notes',
        'bl_number', 'vessel_name', 'voyage_no', 'eta_at', 'carrier_tracking_url',
        'dest_country_id', 'dest_port_id', 'ship_to_name', 'ship_to_phone',
        'ship_to_address_line1', 'ship_to_address_line2', 'ship_to_city', 'ship_to_state', 'ship_to_postcode',
        'cif_freight', 'cif_insurance', 'cif_total',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'cif_freight' => 'decimal:2',
        'cif_insurance' => 'decimal:2',
        'cif_total' => 'decimal:2',
        'payment_payload' => 'array',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'eta_at' => 'date',
    ];

    public const STATUSES = [
        'pending' => 'Pending payment',
        'paid' => 'Paid',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_no)) {
                $order->order_no = 'TOCO-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function destCountry(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Country::class, 'dest_country_id');
    }

    public function destPort(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Port::class, 'dest_port_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class)->oldest();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'processing', 'shipped', 'delivered'], true);
    }

    /**
     * Transition the order to the given status, stamp the matching
     * timestamp, and fire the customer notification (best-effort — mailer
     * failures are logged, not surfaced to the admin user).
     */
    public function transitionTo(string $status): void
    {
        if (! array_key_exists($status, self::STATUSES)) {
            return;
        }
        $previous = $this->status;
        if ($previous === $status) {
            return;
        }

        $this->status = $status;
        match ($status) {
            'paid' => $this->paid_at ??= now(),
            'shipped' => $this->shipped_at = now(),
            'delivered' => $this->delivered_at = now(),
            'cancelled' => $this->cancelled_at = now(),
            default => null,
        };
        $this->save();

        try {
            $this->user->notify(new \App\Notifications\OrderStatusChanged($this, $previous));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OrderStatusChanged notify failed: '.$e->getMessage());
        }
    }
}
