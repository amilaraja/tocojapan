<?php

namespace App\Models;

use App\Support\LiteSpeedCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class ImportRegulation extends Model
{
    protected $guarded = [];

    public const PAYMENT_LC = 'lc';

    public const PAYMENT_OTHER = 'other';

    /** @var array<string, string> */
    public const PAYMENT_MODES = [
        self::PAYMENT_LC => 'LC (Letter of Credit)',
        self::PAYMENT_OTHER => 'Other (T/T, PayPal …)',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'integer',
        'payment_modes' => 'array',
    ];

    public function allowsLc(): bool
    {
        return in_array(self::PAYMENT_LC, (array) ($this->payment_modes ?? []), true);
    }

    /**
     * The active rule that applies to a port: a rule naming the port wins
     * over a country-wide rule (no ports), same as the vehicle page shows.
     */
    public static function forPort(Port $port): ?self
    {
        $rules = static::query()
            ->where('country_id', $port->country_id)
            ->where('is_active', true)
            ->with('ports:id')
            ->orderBy('sort_order')
            ->get();

        return $rules->first(fn (self $r) => $r->ports->contains('id', $port->id))
            ?? $rules->first(fn (self $r) => $r->ports->isEmpty());
    }

    /**
     * IDs of active ports whose applicable rule accepts LC — one pass over
     * all rules instead of a query per port.
     *
     * @return array<int, true>
     */
    public static function lcPortIds(): array
    {
        $rules = static::query()->where('is_active', true)->with('ports:id')->orderBy('sort_order')->get()->groupBy('country_id');
        $ids = [];
        foreach (Port::query()->where('is_active', true)->get(['id', 'country_id']) as $port) {
            $countryRules = $rules->get($port->country_id, collect());
            $rule = $countryRules->first(fn (self $r) => $r->ports->contains('id', $port->id))
                ?? $countryRules->first(fn (self $r) => $r->ports->isEmpty());
            if ($rule?->allowsLc()) {
                $ids[$port->id] = true;
            }
        }

        return $ids;
    }

    /**
     * Whether any destination accepts LC at all — drives the "Paying by LC?"
     * prompt on vehicle pages. Cached 10 min; reset when a rule is saved.
     */
    public static function anyLcDestination(): bool
    {
        return Cache::remember('import_regulations.any_lc', now()->addMinutes(10), fn () => static::lcPortIds() !== []);
    }

    protected static function booted(): void
    {
        // Vehicle pages are full-page cached: purge so the LC prompt/button
        // follows the admin's change straight away.
        $forget = function (): void {
            Cache::forget('import_regulations.any_lc');
            LiteSpeedCache::flagPurge();
        };
        static::saved($forget);
        static::deleted($forget);
    }

    /** True when buyers shipping to this port may pay by LC. */
    public static function portAllowsLc(Port $port): bool
    {
        return (bool) static::forPort($port)?->allowsLc();
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsToMany<Port, $this> */
    public function ports(): BelongsToMany
    {
        return $this->belongsToMany(Port::class);
    }
}
