<?php

namespace App\Services\Holds;

use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Support\LiteSpeedCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place a car is claimed, released or sold (SRS-TOC-PAPI section 7).
 * Every channel (PayPal, bank transfer, proforma, admin, partners) goes
 * through claim(): a row lock on the vehicle plus the unique
 * active_vehicle_id index make the first claim win and every other fail
 * (FR-HLD-01, NFR-10).
 */
class HoldService
{
    /** Set while this service writes vehicle status, so the model hook stands aside. */
    private static bool $writing = false;

    public static function enforced(): bool
    {
        return (bool) config('holds.enforced');
    }

    /**
     * Claim the car. Returns the new hold, or the existing one when the same
     * holder claims again (same website buyer, same partner, or a replayed
     * idempotency key).
     *
     * @throws VehicleUnavailable
     */
    public function claim(Vehicle $vehicle, HoldClaim $claim): VehicleHold
    {
        return DB::transaction(function () use ($vehicle, $claim) {
            $locked = $this->lock($vehicle);

            if ($claim->idempotencyKey !== null && $claim->partner !== null) {
                $replay = VehicleHold::where('partner_id', $claim->partner->id)
                    ->where('vehicle_id', $locked->id)
                    ->where('idempotency_key', $claim->idempotencyKey)
                    ->first();
                if ($replay) {
                    return $replay;
                }
            }

            $current = $this->currentHold($locked);

            if ($current !== null) {
                if (! $this->sameHolder($current, $claim)) {
                    throw new VehicleUnavailable(VehicleUnavailable::HELD, $current, 'This vehicle is on hold for another buyer.');
                }

                return $this->refreshForSameHolder($current, $claim);
            }

            if ($locked->status !== 'published') {
                throw new VehicleUnavailable(VehicleUnavailable::NOT_FOR_SALE, null, 'This vehicle is not for sale.');
            }

            if ($claim->partner !== null && $claim->channel === VehicleHold::CHANNEL_PARTNER) {
                $active = VehicleHold::active()->where('partner_id', $claim->partner->id)->count();
                if ($active >= $claim->partner->max_active_holds) {
                    throw new VehicleUnavailable(VehicleUnavailable::HOLD_LIMIT, null, 'Active hold limit reached.');
                }
            }

            $hold = VehicleHold::create([
                'vehicle_id' => $locked->id,
                'active_vehicle_id' => $locked->id,
                'channel' => $claim->channel,
                'partner_id' => $claim->partner?->id,
                'referral_partner_id' => $claim->referralPartner?->id,
                'user_id' => $claim->buyer?->id,
                'created_by' => $claim->actor?->id,
                'order_id' => $claim->order?->id,
                'proforma_invoice_id' => $claim->proforma?->id,
                'buyer_ref' => mb_substr($claim->buyerRef, 0, 120),
                'destination_country' => $claim->destinationCountry,
                'price_snapshot' => $locked->effectivePriceFob(), // FR-HLD-06
                'currency' => $locked->currency ?: 'USD',
                'idempotency_key' => $claim->idempotencyKey,
                'state' => VehicleHold::STATE_ACTIVE,
                'expires_at' => now()->addHours($claim->hoursOrDefault()),
            ]);

            $this->setStatus($locked, 'reserved');

            return $hold;
        });
    }

    public function release(VehicleHold $hold, string $reason): VehicleHold
    {
        return $this->end($hold, VehicleHold::STATE_RELEASED, $reason);
    }

    public function expire(VehicleHold $hold): VehicleHold
    {
        return $this->end($hold, VehicleHold::STATE_EXPIRED, 'Hold time ran out');
    }

    /** Admin extension (UI brief section 3). A paid hold has no expiry and is left alone. */
    public function extend(VehicleHold $hold, int $hours, ?string $reason = null): VehicleHold
    {
        return DB::transaction(function () use ($hold, $hours, $reason) {
            $hold = VehicleHold::whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if (! $hold->isActive() || $hold->expires_at === null) {
                return $hold;
            }

            $from = $hold->expires_at->isFuture() ? $hold->expires_at : now();
            $hold->forceFill([
                'expires_at' => $from->copy()->addHours($hours),
                'extensions' => $hold->extensions + 1,
                'expiry_warned_at' => null,
                'ended_reason' => $reason ? 'Extended: '.$reason : $hold->ended_reason,
            ])->save();

            return $hold;
        });
    }

    /** Money arrived: keep the car until staff confirm the sale (no expiry). */
    public function awaitConfirmation(VehicleHold $hold): void
    {
        if ($hold->isActive() && $hold->expires_at !== null) {
            $hold->forceFill(['expires_at' => null])->save();
        }
    }

    /**
     * Staff confirm the sale behind this hold (FR-HLD-05): the car becomes
     * sold and the hold is closed as converted.
     */
    public function confirmSale(VehicleHold $hold, ?User $actor = null): VehicleHold
    {
        return DB::transaction(function () use ($hold, $actor) {
            $vehicle = $this->lock($hold->vehicle);
            $hold = VehicleHold::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if (! $hold->isActive()) {
                throw new VehicleUnavailable(VehicleUnavailable::NOT_FOR_SALE, null, 'That hold is no longer active.');
            }

            $hold->forceFill([
                'state' => VehicleHold::STATE_CONVERTED,
                'active_vehicle_id' => null,
                'ended_at' => now(),
                'ended_reason' => 'Sale confirmed'.($actor ? ' by '.$actor->name : ''),
            ])->save();

            $this->setStatus($vehicle, 'sold');

            return $hold;
        });
    }

    /**
     * Sale with no hold yet (walk-in, a listing partner's referral): claim
     * and confirm in one step, so the same lock rules apply.
     */
    public function sellUnheld(Vehicle $vehicle, HoldClaim $claim): VehicleHold
    {
        return DB::transaction(fn () => $this->confirmSale($this->claim($vehicle, $claim), $claim->actor));
    }

    /** Scheduler, every minute (FR-HLD-04). */
    public function expireDue(): int
    {
        $count = 0;
        VehicleHold::active()->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->orderBy('id')->each(function (VehicleHold $hold) use (&$count) {
                $this->expire($hold);
                $count++;
            });

        return $count;
    }

    public function activeHold(Vehicle $vehicle): ?VehicleHold
    {
        return VehicleHold::active()->where('vehicle_id', $vehicle->id)->first();
    }

    /**
     * Before PayPal captures money: the order must still own the car, or be
     * able to win it back. False means do not take the payment.
     */
    public function ensureForOrder(Order $order): bool
    {
        if (! self::enforced()) {
            return true;
        }
        if (VehicleHold::active()->where('order_id', $order->id)->exists()) {
            return true;
        }

        try {
            $this->claim($order->vehicle, HoldClaim::forOrder($order));

            return true;
        } catch (VehicleUnavailable) {
            return false;
        }
    }

    /** Order status changed (model hook): paid keeps the car, cancelled frees it. */
    public function syncOrder(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $hold = VehicleHold::active()->where('order_id', $order->id)->first();

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            if ($hold) {
                $this->release($hold, 'Order '.$order->order_no.' '.$order->status);
            }

            return;
        }

        if ($order->status === 'paid' && self::enforced()) {
            if (! $hold && $this->ensureForOrder($order)) {
                $hold = VehicleHold::active()->where('order_id', $order->id)->first();
            }

            if ($hold) {
                $this->awaitConfirmation($hold);

                return;
            }

            // Money arrived but another channel holds the car: never silent.
            $note = 'PAID BUT THE CAR IS HELD BY ANOTHER CHANNEL. Resolve before shipping (refund or release the other hold).';
            Order::withoutEvents(fn () => $order->forceFill([
                'admin_notes' => trim((string) $order->admin_notes."\n".now()->toDateTimeString().' - '.$note),
            ])->save());
            Log::error('Order paid without a hold on its vehicle', ['order_no' => $order->order_no, 'vehicle_id' => $order->vehicle_id]);
        }
    }

    /**
     * Vehicle status changed outside this service (admin edit form): keep
     * the hold table in step. Sold converts the hold; anything else ends it.
     */
    public function syncVehicleStatus(Vehicle $vehicle): void
    {
        if (self::$writing || ! $vehicle->wasChanged('status') || $vehicle->getOriginal('status') !== 'reserved') {
            return;
        }

        $hold = $this->activeHold($vehicle);
        if (! $hold) {
            return;
        }

        if ($vehicle->status === 'sold') {
            $hold->forceFill([
                'state' => VehicleHold::STATE_CONVERTED,
                'active_vehicle_id' => null,
                'ended_at' => now(),
                'ended_reason' => 'Marked sold in the vehicle form',
            ])->save();

            return;
        }

        $hold->forceFill([
            'state' => VehicleHold::STATE_RELEASED,
            'active_vehicle_id' => null,
            'ended_at' => now(),
            'ended_reason' => 'Status changed to '.$vehicle->status.' in the vehicle form',
        ])->save();
    }

    private function end(VehicleHold $hold, string $state, string $reason): VehicleHold
    {
        return DB::transaction(function () use ($hold, $state, $reason) {
            $vehicle = $this->lock($hold->vehicle);
            $hold = VehicleHold::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if (! $hold->isActive()) {
                return $hold;
            }

            $hold->forceFill([
                'state' => $state,
                'active_vehicle_id' => null,
                'ended_at' => now(),
                'ended_reason' => $reason,
            ])->save();

            if ($vehicle->status === 'reserved') {
                $this->setStatus($vehicle, 'published');
            }

            return $hold;
        });
    }

    /** The active hold, after lapsing it if its time ran out (no wait for the scheduler). */
    private function currentHold(Vehicle $locked): ?VehicleHold
    {
        $current = VehicleHold::active()->where('vehicle_id', $locked->id)->lockForUpdate()->first();

        if ($current && $current->expires_at !== null && $current->expires_at->isPast()) {
            $this->end($current, VehicleHold::STATE_EXPIRED, 'Hold time ran out');
            $locked->refresh();

            return null;
        }

        return $current;
    }

    private function sameHolder(VehicleHold $current, HoldClaim $claim): bool
    {
        if ($current->isWeb() && $claim->isWeb()) {
            return $claim->buyer !== null && $current->user_id === $claim->buyer->id;
        }

        return $current->channel === VehicleHold::CHANNEL_PARTNER
            && $claim->channel === VehicleHold::CHANNEL_PARTNER
            && $claim->partner !== null
            && $current->partner_id === $claim->partner->id;
    }

    /** A website buyer moving between checkout methods keeps one hold. */
    private function refreshForSameHolder(VehicleHold $current, HoldClaim $claim): VehicleHold
    {
        if (! $current->isWeb()) {
            return $current;
        }

        $changes = array_filter([
            'order_id' => $claim->order?->id,
            'proforma_invoice_id' => $claim->proforma?->id,
        ]);
        if ($claim->order !== null) {
            $changes['channel'] = $claim->channel;
            $changes['buyer_ref'] = $claim->buyerRef;
        }
        if ($current->expires_at !== null) {
            $until = now()->addHours($claim->hoursOrDefault());
            if ($until->greaterThan($current->expires_at)) {
                $changes['expires_at'] = $until;
            }
        }

        if ($changes !== []) {
            $current->forceFill($changes)->save();
        }

        return $current;
    }

    private function lock(Vehicle $vehicle): Vehicle
    {
        $locked = Vehicle::withTrashed()->whereKey($vehicle->id)->lockForUpdate()->first();

        if (! $locked || $locked->trashed()) {
            throw new VehicleUnavailable(VehicleUnavailable::NOT_FOR_SALE, null, 'This vehicle is not for sale.');
        }

        return $locked;
    }

    private function setStatus(Vehicle $vehicle, string $status): void
    {
        self::$writing = true;
        try {
            $vehicle->forceFill(['status' => $status])->save();
        } finally {
            self::$writing = false;
        }

        // Cached pages must not keep offering a reserved or sold car.
        if (! app()->runningUnitTests()) {
            app()->runningInConsole() ? LiteSpeedCache::queuePurge() : LiteSpeedCache::flagPurge();
        }
    }
}
