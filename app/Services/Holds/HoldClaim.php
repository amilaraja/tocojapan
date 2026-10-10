<?php

namespace App\Services\Holds;

use App\Models\Order;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Models\Partner;

/** Who wants the car, through which channel, and for how long. */
final class HoldClaim
{
    public function __construct(
        public readonly string $channel,
        public readonly string $buyerRef,
        public readonly ?int $hours = null,
        public readonly ?User $buyer = null,
        public readonly ?User $actor = null,
        public readonly ?Partner $partner = null,
        public readonly ?Partner $referralPartner = null,
        public readonly ?Order $order = null,
        public readonly ?ProformaInvoice $proforma = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $destinationCountry = null,
    ) {}

    public static function forOrder(Order $order): self
    {
        return new self(
            channel: $order->payment_provider === 'paypal' ? VehicleHold::CHANNEL_WEB_PAYPAL : VehicleHold::CHANNEL_WEB_TT,
            buyerRef: $order->order_no,
            buyer: $order->user,
            order: $order,
            destinationCountry: $order->destCountry?->iso2,
        );
    }

    public function isWeb(): bool
    {
        return in_array($this->channel, VehicleHold::WEB_CHANNELS, true);
    }

    public function hoursOrDefault(): int
    {
        return $this->hours
            ?? ($this->channel === VehicleHold::CHANNEL_PARTNER ? $this->partner?->hold_hours : null)
            ?? (int) config('holds.hours.'.$this->channel, 48);
    }
}
