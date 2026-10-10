<?php

namespace App\Modules\PartnerApi\Domain\Stock;

use App\Models\Vehicle;
use App\Modules\PartnerApi\Models\Partner;

/**
 * Partner prices, computed server-side (FR-STK-04). With a markup the
 * partner only ever sees the final price, never Toco's base price.
 */
class PartnerPricing
{
    public const AS_IS = 'as_is';

    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    public const ON_REQUEST = 'on_request';

    /** @return array{amount: float|null, currency: string, basis: string, on_request: bool, reason: string|null} */
    public static function price(Vehicle $vehicle, Partner $partner): array
    {
        $base = $vehicle->price_on_request ? null : $vehicle->effectivePriceFob();
        $amount = $base !== null && (float) $base > 0 ? self::apply((float) $base, $partner) : null;

        $reason = match (true) {
            $partner->price_mode === self::ON_REQUEST => 'partner_price_on_request',
            $amount === null => 'price_on_request',
            default => null,
        };

        return [
            'amount' => $reason === null ? $amount : null,
            'currency' => $vehicle->currency ?: 'USD',
            'basis' => 'FOB',
            'on_request' => $reason !== null,
            'reason' => $reason,
        ];
    }

    public static function apply(float $base, Partner $partner): float
    {
        $markup = (float) ($partner->price_markup ?? 0);

        return round(match ($partner->price_mode) {
            self::PERCENT => $base * (1 + $markup / 100),
            self::FIXED => $base + $markup,
            default => $base,
        }, 0);
    }

    /** A partner-facing price bound converted back to Toco's base price, for filters. */
    public static function toBase(float $partnerPrice, Partner $partner): float
    {
        $markup = (float) ($partner->price_markup ?? 0);

        return match ($partner->price_mode) {
            self::PERCENT => $partnerPrice / (1 + $markup / 100),
            self::FIXED => $partnerPrice - $markup,
            default => $partnerPrice,
        };
    }

    /** Short admin label, e.g. "+8% markup" (UI brief 2.1). */
    public static function label(Partner $partner): string
    {
        $markup = (float) ($partner->price_markup ?? 0);

        return match ($partner->price_mode) {
            self::PERCENT => '+'.rtrim(rtrim(number_format($markup, 2), '0'), '.').'% markup',
            self::FIXED => 'Fixed +USD '.number_format($markup),
            self::ON_REQUEST => 'Price on request',
            default => 'Toco price',
        };
    }
}
