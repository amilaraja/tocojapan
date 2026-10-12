<?php

namespace App\Modules\Crm\Domain;

use App\Models\User;
use App\Models\VehicleHold;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Support\CrmSettings;
use App\Services\Holds\HoldClaim;
use App\Services\Holds\HoldService;
use App\Services\Holds\VehicleUnavailable;
use Illuminate\Validation\ValidationException;

/**
 * What ticking a step does beyond the tick itself: the car hold, the order
 * status (CRM-STG-009), SLA clocks and the buyer emails of SRS §8.1.
 */
class StepEffects
{
    /**
     * Runs before the tick is saved; may refuse it.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    public static function before(CrmCase $case, string $key, array &$data, ?User $by, bool $auto, array $context): void
    {
        if ($auto) {
            return;
        }

        match ($key) {
            'vehicle_reserved' => self::reserve($case, $by, $data),
            'vehicle_sold' => self::markSold($case, $by),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    public static function after(CrmCase $case, string $key, array $data, ?User $by, bool $auto, array $context): void
    {
        $fromOrder = (bool) ($context['from_order'] ?? false);
        // While the CRM replaces the old order emails, it emails even when the change came from the order screen.
        $customerContext = $context + ['skip_customer' => $fromOrder && ! CrmSettings::get('email.replace_order_emails')];

        switch ($key) {
            case 'acknowledged':
                // A buy-now order confirmation ticks the step, but staff still owe the buyer a personal reply.
                if ($context['keep_first_response'] ?? false) {
                    break;
                }
                SlaClock::meet($case, 'first_response');
                $case->forceFill(['first_response_at' => $case->first_response_at ?? now()])->saveQuietly();
                break;

            case 'quote_sent':
                SlaClock::meet($case, 'quote_sent');
                if (isset($data['price']) && (float) $data['price'] > 0) {
                    $case->forceFill(['value_usd' => (float) $data['price'], 'value_basis' => $data['basis'] ?? $case->value_basis ?? 'CIF'])->saveQuietly();
                }
                break;

            case 'payment_received':
                if (! empty($data['payment_method'])) {
                    $case->forceFill(['payment_mode' => $data['payment_method']])->saveQuietly();
                }
                SlaClock::meet($case, 'payment');
                if (! $fromOrder) {
                    OrderSync::to($case, 'paid');
                }
                Notifier::event('payment_received', $case, $customerContext);
                // The payment email is the receipt (SRS §3.2 "Sent with the payment received email").
                if (! $customerContext['skip_customer'] && Notifier::enabled('payment_received', 'customer_email') && $case->customer?->email) {
                    StageEngine::complete($case, 'receipt_sent', null, [], true);
                }
                break;

            case 'vessel_booked':
                SlaClock::meet($case, 'vessel_booked');
                if (! $fromOrder) {
                    OrderSync::to($case, 'processing');
                }
                Notifier::event('vessel_booked', $case, $customerContext);
                break;

            case 'bl_issued':
                if (! $fromOrder) {
                    $vessel = StageEngine::stepData($case)['vessel_booked'] ?? [];
                    OrderSync::to($case, 'shipped', array_filter([
                        'bl_number' => $data['bl_number'] ?? null,
                        'vessel_name' => $vessel['vessel_name'] ?? null,
                        'voyage_no' => $vessel['voyage_no'] ?? null,
                        'eta_at' => $data['eta'] ?? null,
                    ]));
                }
                SlaClock::start($case, 'documents_couriered');
                Notifier::event('bl_issued', $case, $customerContext);
                break;

            case 'documents_couriered':
                SlaClock::meet($case, 'documents_couriered');
                Notifier::event('documents_couriered', $case, $customerContext);
                break;

            case 'vessel_arrived':
                Notifier::event('vessel_arrived', $case, $customerContext);
                break;

            case 'car_received':
                if (! $fromOrder) {
                    OrderSync::to($case, 'delivered');
                }
                break;
        }
    }

    /** Clocks that start when a stage starts (SRS §7.1). @param array<string, mixed> $context */
    public static function enteredStage(CrmCase $case, int $stage, ?User $by, array $context): void
    {
        if ($context['back'] ?? false) {
            return;
        }

        match ($stage) {
            2 => self::startPayment($case),
            3 => [SlaClock::cancel($case, 'payment'), SlaClock::start($case, 'vessel_booked')],
            default => null,
        };
    }

    private static function startPayment(CrmCase $case): void
    {
        SlaClock::cancel($case, 'quote_sent');
        SlaClock::start($case, 'payment');
    }

    /** Reserve the car for this buyer through the hold service (FR-HLD-03). @param array<string, mixed> $data */
    private static function reserve(CrmCase $case, ?User $by, array &$data): void
    {
        $vehicle = $case->vehicle;
        if ($vehicle === null) {
            throw ValidationException::withMessages(['data' => 'Link a vehicle to this Case before reserving it.']);
        }

        $holds = app(HoldService::class);
        $current = $holds->activeHold($vehicle);
        if ($current && $current->buyer_ref === $case->reference) {
            $data['hold_id'] = $current->id;

            return;
        }
        if ($vehicle->status === 'sold') {
            throw ValidationException::withMessages(['data' => 'This vehicle is already sold.']);
        }

        try {
            $hold = $holds->claim($vehicle, new HoldClaim(
                channel: VehicleHold::CHANNEL_ADMIN,
                buyerRef: $case->reference,
                hours: max(24, min(96, CrmSettings::int('sla.payment_due_days') * 24)),
                buyer: $case->customer?->user,
                actor: $by,
                destinationCountry: $case->destCountry?->iso2,
            ));
            $data['hold_id'] = $hold->id;
        } catch (VehicleUnavailable $e) {
            throw ValidationException::withMessages(['data' => $e->getMessage()]);
        }
    }

    /** Mark the car sold on the website through the hold service. */
    private static function markSold(CrmCase $case, ?User $by): void
    {
        $vehicle = $case->vehicle;
        if ($vehicle === null || $vehicle->status === 'sold') {
            return;
        }

        $holds = app(HoldService::class);
        try {
            $current = $holds->activeHold($vehicle);
            if ($current) {
                $holds->confirmSale($current, $by);
            } else {
                $holds->sellUnheld($vehicle, new HoldClaim(channel: VehicleHold::CHANNEL_ADMIN, buyerRef: $case->reference, actor: $by));
            }
        } catch (VehicleUnavailable $e) {
            throw ValidationException::withMessages(['data' => $e->getMessage()]);
        }
    }
}
