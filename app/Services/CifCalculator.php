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
     *   insurance   = settings.cif.marine_insurance_usd (flat USD, default $35)
     *   cif_total   = price_fob + freight + insurance
     *
     * @return array{
     *   price_fob: float,
     *   freight: float,
     *   insurance: float,
     *   cif_total: float,
     *   currency: string,
     *   m3: float,
     *   rate_per_m3: float,
     *   port: array{id:int, name:string, country:string},
     * }
     */
    public function calculate(
        float $priceFob,
        float $m3,
        Port $port,
        ?string $currency = null,
    ): array {
        if ($priceFob < 0) {
            throw new \InvalidArgumentException('price_fob must be >= 0');
        }
        if ($m3 <= 0) {
            throw new \InvalidArgumentException('m3 must be > 0');
        }

        $ratePerM3 = (float) $port->rate_per_m3;
        $insurance = round((float) $this->settings->marine_insurance_usd, 2);

        $freight = round($m3 * $ratePerM3, 2);
        $cifTotal = round($priceFob + $freight + $insurance, 2);

        return [
            'price_fob' => round($priceFob, 2),
            'freight' => $freight,
            'insurance' => $insurance,
            'cif_total' => $cifTotal,
            'currency' => $currency ?? $this->settings->default_currency,
            'm3' => round($m3, 4),
            'rate_per_m3' => $ratePerM3,
            'port' => [
                'id' => $port->id,
                'name' => $port->name,
                'country' => $port->country?->name ?? '',
            ],
        ];
    }
}
