<?php

namespace App\Modules\PartnerApi\Domain\Webhooks;

use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerWebhookDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns vehicle and hold changes into change notifications (FR-SYNC-02).
 * Payloads are thin: the event, stock number and status. Partners fetch the
 * vehicle itself through the API, so nothing outside their scope leaks.
 * Events are judged from each partner's point of view: a car entering its
 * scope is "created" for it, a car leaving is "removed".
 */
class WebhookOutbox
{
    public const VEHICLE_CREATED = 'vehicle.created';

    public const VEHICLE_UPDATED = 'vehicle.updated';

    public const VEHICLE_STATUS_CHANGED = 'vehicle.status_changed';

    public const VEHICLE_SOLD = 'vehicle.sold';

    public const VEHICLE_REMOVED = 'vehicle.removed';

    public const HOLD_EXPIRING = 'hold.expiring';

    public const HOLD_EXPIRED = 'hold.expired';

    public const HOLD_RELEASED = 'hold.released';

    public const PING = 'ping';

    /** Fields partners show; a change to anything else is not worth a notification. */
    private const WATCHED = ['title', 'status', 'price_fob', 'price_fob_discount', 'price_on_request', 'currency', 'mileage_km',
        'make_id', 'vehicle_model_id', 'body_type_id', 'year_first_reg', 'manufacture_year', 'm3', 'deleted_at', 'updated_at'];

    /** Called from the Vehicle created / updated / deleted model hooks. */
    public static function vehicleChanged(Vehicle $vehicle, bool $deleted = false, bool $created = false): void
    {
        $partners = self::subscribers();
        if ($partners->isEmpty()) {
            return;
        }

        $changed = $created || $deleted || array_intersect(array_keys($vehicle->getChanges()), self::WATCHED) !== [];
        if (! $changed) {
            return;
        }

        // A soft delete syncs the originals before "deleted" fires: judge the
        // car as it was just before, i.e. not yet deleted.
        $before = match (true) {
            $created => null,
            $deleted => (new Vehicle)->forceFill(['deleted_at' => null] + $vehicle->getRawOriginal()),
            default => (new Vehicle)->forceFill($vehicle->getRawOriginal()),
        };

        foreach ($partners as $partner) {
            $was = $before !== null && PartnerScope::matches($partner, $before);
            $is = ! $deleted && PartnerScope::matches($partner, $vehicle);

            $event = match (true) {
                ! $was && ! $is => null,
                ! $was && $is => self::VEHICLE_CREATED,
                $was && ! $is => self::VEHICLE_REMOVED,
                $vehicle->wasChanged('status') && $vehicle->status === 'sold' => self::VEHICLE_SOLD,
                $vehicle->wasChanged('status') => self::VEHICLE_STATUS_CHANGED,
                default => self::VEHICLE_UPDATED,
            };

            if ($event !== null) {
                self::push($partner, $event, $vehicle->stock_no, [
                    'stock_no' => $vehicle->stock_no,
                    'status' => $event === self::VEHICLE_REMOVED ? 'removed' : PartnerScope::status($vehicle),
                    'updated_at' => now()->toIso8601ZuluString(),
                ]);
            }
        }
    }

    /** Hold notices go to the selling partner that holds the car only. */
    public static function holdEvent(VehicleHold $hold, string $event): void
    {
        $partner = $hold->partner;
        if (! $partner || ! $partner->webhook_url || ! $partner->isActive()) {
            return;
        }

        self::push($partner, $event, $hold->public_id, [
            'hold_id' => $hold->public_id,
            'stock_no' => $hold->vehicle?->stock_no,
            'buyer_ref' => $hold->buyer_ref,
            'expires_at' => $hold->expires_at?->toIso8601ZuluString(),
            'reason' => $event === self::HOLD_RELEASED ? 'Released by Toco' : null,
        ]);
    }

    /**
     * Queue one delivery. A still-unsent delivery about the same subject is
     * replaced by the newer one: the partner only needs the latest state.
     *
     * @param  array<string, mixed>  $data
     */
    public static function push(Partner $partner, string $event, ?string $subject, array $data): PartnerWebhookDelivery
    {
        $deliveryId = 'whd_'.strtolower((string) Str::ulid());
        $payload = ['id' => $deliveryId, 'type' => $event, 'created_at' => now()->toIso8601ZuluString(), 'data' => array_filter($data, fn ($v) => $v !== null)];

        $pending = $subject === null || str_starts_with($event, 'hold.') ? null : PartnerWebhookDelivery::where('partner_id', $partner->id)
            ->where('subject', $subject)->where('status', PartnerWebhookDelivery::PENDING)->where('attempts', 0)
            ->where('event', 'like', 'vehicle.%')->first();

        if ($pending) {
            $pending->forceFill(['event' => $event, 'payload' => ['id' => $pending->delivery_id] + $payload])->save();

            return $pending;
        }

        return PartnerWebhookDelivery::create([
            'partner_id' => $partner->id,
            'delivery_id' => $deliveryId,
            'event' => $event,
            'subject' => $subject,
            'payload' => $payload,
            'status' => PartnerWebhookDelivery::PENDING,
            'next_attempt_at' => now(),
        ]);
    }

    /** @return Collection<int, Partner> */
    private static function subscribers(): Collection
    {
        return Partner::query()->where('status', Partner::STATUS_ACTIVE)->whereNotNull('webhook_url')->get()
            ->filter(fn (Partner $partner) => $partner->accessDenialCode() === null)->values();
    }
}
