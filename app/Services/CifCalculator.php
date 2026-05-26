<?php

namespace App\Services;

use App\Models\Port;
use App\Settings\CifSettings;

class CifCalculator
{
    public function __construct(private readonly CifSettings $settings) {}

    /**
     * Compute CIF breakdown for a single vehicle to a destination port.
     *
     * Formula:
     *   freight     = m3 × port.rate_per_m3
     *   insurance   = (price_fob + freight) × insurance_pct
     *   cif_total   = price_fob + freight + insurance
     *
     * Insurance pct precedence: $override → $port->insurance_pct → settings.cif.insurance_pct.
     *
     * @return array{
     *   price_fob: float,
     *   freight: float,
     *   insurance: float,
     *   cif_total: float,
     *   currency: string,
     *   m3: float,
     *   rate_per_m3: float,
     *   insurance_pct: float,
     *   port: array{id:int, name:string, country:string},
     * }
     */
    public function calculate(
        float $priceFob,
        float $m3,
        Port $port,
        ?string $currency = null,
        ?float $insurancePctOverride = null,
    ): array {
        if ($priceFob < 0) {
            throw new \InvalidArgumentException('price_fob must be >= 0');
        }
        if ($m3 <= 0) {
            throw new \InvalidArgumentException('m3 must be > 0');
        }

        $insurancePct = $insurancePctOverride
            ?? ($port->insurance_pct !== null ? (float) $port->insurance_pct : null)
            ?? $this->settings->insurance_pct;

        // insurance_pct is a fraction (0.015 = 1.5%). A value >= 1 means the
        // data was entered as a whole-number percentage by mistake — at that
        // scale insurance can dwarf the vehicle price. Clamp it back below 1
        // before it hits the multiplication.
        if ($insurancePct >= 1) {
            $insurancePct = (float) $this->settings->insurance_pct;
        }

        $ratePerM3 = (float) $port->rate_per_m3;

        $freight = round($m3 * $ratePerM3, 2);
        $insurance = round(($priceFob + $freight) * $insurancePct, 2);
        $cifTotal = round($priceFob + $freight + $insurance, 2);

        return [
            'price_fob' => round($priceFob, 2),
            'freight' => $freight,
            'insurance' => $insurance,
            'cif_total' => $cifTotal,
            'currency' => $currency ?? $this->settings->default_currency,
            'm3' => round($m3, 4),
            'rate_per_m3' => $ratePerM3,
            'insurance_pct' => round($insurancePct, 4),
            'port' => [
                'id' => $port->id,
                'name' => $port->name,
                'country' => $port->country?->name ?? '',
            ],
        ];
    }
}
