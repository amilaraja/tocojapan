<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Site-wide defaults for supplier-feed stock (OnePrice …). */
class StockSettings extends Settings
{
    /** Legacy flat margin, %; used only as the fallback for a supplier/import that overrides one part. */
    public float $supplier_margin_percent;

    /** Legacy flat margin, USD; see above. */
    public float $supplier_margin_fixed_usd;

    /**
     * Default profit margin by price band (client change 2026-10-14). The band is chosen by the
     * supplier price converted to USD, before margin.
     *
     * Each tier: from_usd, to_usd (null = and above), percent, fixed_usd.
     * (No @var: spatie/laravel-settings would try to cast the array elements.)
     */
    public array $supplier_margin_tiers;

    public static function group(): string
    {
        return 'stock';
    }

    /** @return list<array{from_usd: float, to_usd: ?float, percent: float, fixed_usd: float}> */
    public function tiers(): array
    {
        return self::normaliseTiers($this->supplier_margin_tiers ?? []);
    }

    /**
     * Clean, sorted tiers (lowest "from" first).
     *
     * @return list<array{from_usd: float, to_usd: ?float, percent: float, fixed_usd: float}>
     */
    public static function normaliseTiers(array $tiers): array
    {
        $out = [];
        foreach ($tiers as $t) {
            if (! is_array($t)) {
                continue;
            }
            $to = $t['to_usd'] ?? null;
            $out[] = [
                'from_usd' => max(0.0, (float) ($t['from_usd'] ?? 0)),
                'to_usd' => ($to === null || $to === '') ? null : (float) $to,
                'percent' => (float) ($t['percent'] ?? 0),
                'fixed_usd' => (float) ($t['fixed_usd'] ?? 0),
            ];
        }
        usort($out, fn ($a, $b) => $a['from_usd'] <=> $b['from_usd']);

        return $out;
    }
}
