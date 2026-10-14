<?php

namespace App\Models;

use App\Settings\StockSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Where a vehicle comes from: Toco's own yard stock or a stock feed
 * (OnePrice, JWT …). Supplier stock lives in the same vehicles table;
 * settings decide how it is priced, published and synced.
 */
class Supplier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_own_stock' => 'bool',
        'is_active' => 'bool',
        'sort_priority' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Defaults for the `settings` JSON. Anything not stored falls back here.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_SETTINGS = [
        // Which supplier price becomes the FOB price: 'retail' or 'wholesale'.
        'price_basis' => 'retail',
        // Use the wholesale price when the retail price is missing.
        'fallback_to_wholesale' => false,
        'source_currency' => 'JPY',
        // FOB (USD) = source price ÷ rate × (1 + margin %) + fixed margin, rounded up to round_to.
        // null margin = use the site default (Site settings → Supplier stock).
        'margin_percent' => null,
        'margin_fixed_usd' => null,
        'round_to' => 10,
        // Source prices at/above this are placeholders ("ASK") → price on request.
        'max_source_price' => 99_000_000,
        // New vehicles go live immediately (otherwise they arrive as drafts).
        'auto_publish_new' => true,
        // PayPal / bank-transfer checkout. Off = quote request only (availability unconfirmed).
        'allow_online_checkout' => false,
        'show_on_homepage' => false,
        'in_sitemap' => false,
        // A full import that would delist more than this % of live stock needs explicit confirmation.
        'delist_guard_percent' => 30,
        // Delisted vehicles with no orders/quotes are removed after this many days (0 = never).
        'purge_delisted_after_days' => 180,
    ];

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return HasMany<SupplierImport, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(SupplierImport::class);
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function resolvedSettings(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    public static function ownStock(): self
    {
        return static::query()->where('is_own_stock', true)->firstOrFail();
    }

    public static function ownStockId(): int
    {
        static $id = null;
        if ($id) {
            return $id;
        }

        return $id = (int) static::query()->where('is_own_stock', true)->value('id');
    }

    /**
     * IDs of suppliers whose stock may appear in a given place
     * ('show_on_homepage', 'in_sitemap', 'allow_online_checkout').
     * Own stock is always included.
     *
     * @return array<int, int>
     */
    public static function idsWithSetting(string $key): array
    {
        return static::query()->get()
            ->filter(fn (Supplier $s) => $s->is_own_stock || (bool) $s->setting($key))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Pick the supplier price that drives the FOB price.
     *
     * @return float|null null → price on request
     */
    public function chooseSourcePrice(?float $retail, ?float $wholesale): ?float
    {
        $primary = $this->setting('price_basis') === 'wholesale' ? $wholesale : $retail;
        $price = $primary;
        if (($price === null || $price <= 0) && $this->setting('fallback_to_wholesale')) {
            $price = $wholesale;
        }
        if ($price === null || $price <= 0 || $price >= (float) $this->setting('max_source_price')) {
            return null;
        }

        return $price;
    }

    /**
     * Profit margin to apply: an import's own override wins, then this
     * supplier's setting, then the site default tiers (Site settings → Supplier stock).
     * A flat override replaces the tiers; each overridden part falls back on its own,
     * so a supplier can override only the % and keep the legacy default fixed amount.
     *
     * @param  array{percent?: float|int|string|null, fixed_usd?: float|int|string|null}|null  $override
     * @return array{percent: float, fixed_usd: float, tiers?: list<array{from_usd: float, to_usd: ?float, percent: float, fixed_usd: float}>}
     */
    public function effectiveMargin(?array $override = null): array
    {
        $site = app(StockSettings::class);
        $parts = [$override['percent'] ?? null, $override['fixed_usd'] ?? null, $this->setting('margin_percent'), $this->setting('margin_fixed_usd')];

        if (! collect($parts)->contains(fn ($v) => self::filled($v)) && ($tiers = $site->tiers()) !== []) {
            return ['percent' => $tiers[0]['percent'], 'fixed_usd' => $tiers[0]['fixed_usd'], 'tiers' => $tiers];
        }

        $pick = fn ($import, $supplier, $default) => (float) (self::filled($import) ? $import : (self::filled($supplier) ? $supplier : $default));

        return [
            'percent' => $pick($override['percent'] ?? null, $this->setting('margin_percent'), $site->supplier_margin_percent),
            'fixed_usd' => $pick($override['fixed_usd'] ?? null, $this->setting('margin_fixed_usd'), $site->supplier_margin_fixed_usd),
        ];
    }

    /**
     * A stored margin (import stats, vehicle meta) in the shape fobFromSource() expects.
     *
     * @return array{percent: float, fixed_usd: float, tiers?: list<array{from_usd: float, to_usd: ?float, percent: float, fixed_usd: float}>}
     */
    public static function normaliseMargin(array $m): array
    {
        $out = ['percent' => (float) ($m['percent'] ?? 0), 'fixed_usd' => (float) ($m['fixed_usd'] ?? 0)];
        if (! empty($m['tiers']) && is_array($m['tiers'])) {
            $out['tiers'] = StockSettings::normaliseTiers($m['tiers']);
        }

        return $out;
    }

    /**
     * The % and fixed amount for a converted (pre-margin) USD price: with tiers, the
     * highest tier whose "from" is at or below the price (the lowest tier below that).
     *
     * @return array{percent: float, fixed_usd: float}
     */
    public static function marginFor(array $margin, float $baseUsd): array
    {
        $tiers = $margin['tiers'] ?? [];
        if ($tiers === []) {
            return ['percent' => (float) $margin['percent'], 'fixed_usd' => (float) $margin['fixed_usd']];
        }
        $hit = $tiers[0];
        foreach ($tiers as $t) {
            if ($baseUsd >= $t['from_usd']) {
                $hit = $t;
            }
        }

        return ['percent' => (float) $hit['percent'], 'fixed_usd' => (float) $hit['fixed_usd']];
    }

    /** Short admin label, e.g. "10% + $0 per vehicle" or "$0–2,000: 20%; $2,001+: $200". */
    public static function describeMargin(array $m): string
    {
        $flat = fn ($pct, $fix) => collect([
            (float) $pct ? rtrim(rtrim(number_format((float) $pct, 2), '0'), '.').'%' : null,
            (float) $fix ? '$'.number_format((float) $fix) : null,
        ])->filter()->implode(' + ') ?: '0%';

        if (! empty($m['tiers'])) {
            return collect($m['tiers'])->map(fn ($t) => '$'.number_format($t['from_usd'])
                .($t['to_usd'] !== null ? '–'.number_format($t['to_usd']) : '+').': '.$flat($t['percent'], $t['fixed_usd']))
                ->implode('; ');
        }

        return rtrim(rtrim(number_format((float) $m['percent'], 2), '0'), '.').'% + $'.number_format((float) $m['fixed_usd']).' per vehicle';
    }

    private static function filled(mixed $v): bool
    {
        return $v !== null && $v !== '';
    }

    /**
     * USD FOB price for a supplier source price: converted at the current
     * exchange rate, then the margin (% first, then the fixed USD amount; with
     * tiers, the tier for the converted price), rounded up to the supplier's step.
     *
     * @param  array{percent: float, fixed_usd: float, tiers?: array}|null  $margin  null = effectiveMargin()
     */
    public function fobFromSource(?float $source, ?float $rateToUsd = null, ?array $margin = null): ?float
    {
        if ($source === null || $source <= 0) {
            return null;
        }
        $rate = $rateToUsd ?? $this->sourceRateToUsd();
        if (! $rate || $rate <= 0) {
            return null;
        }
        $base = $source / $rate;
        $m = self::marginFor($margin ?? $this->effectiveMargin(), $base);
        $usd = $base * (1 + $m['percent'] / 100) + $m['fixed_usd'];
        $step = max(1, (int) $this->setting('round_to'));

        // round() first so float noise (16500.000000002) doesn't bump a step.
        return (float) (ceil(round($usd / $step, 6)) * $step);
    }

    /** Units of the source currency per 1 USD, from the currencies table. */
    public function sourceRateToUsd(): ?float
    {
        $code = strtoupper((string) $this->setting('source_currency'));
        if ($code === 'USD') {
            return 1.0;
        }
        $rate = DB::table('currencies')->where('code', $code)->value('rate_to_usd');

        return $rate ? (float) $rate : null;
    }
}
