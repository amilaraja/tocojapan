<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyRates
{
    public const SOURCE_URL = 'https://open.er-api.com/v6/latest/USD';

    /**
     * Fetch latest USD→X rates and persist to the currencies table.
     */
    public function fetch(): int
    {
        $response = Http::timeout(15)->get(self::SOURCE_URL);
        if (! $response->successful()) {
            Log::warning('Currency rate fetch failed', ['status' => $response->status()]);

            return 0;
        }

        $body = $response->json();
        if (($body['result'] ?? '') !== 'success' || empty($body['rates'])) {
            Log::warning('Currency rate response malformed', ['body' => $body]);

            return 0;
        }

        $rates = $body['rates'];
        $updated = 0;
        foreach (Currency::query()->where('is_active', true)->get() as $currency) {
            if (! isset($rates[$currency->code])) {
                continue;
            }
            $currency->rate_to_usd = (float) $rates[$currency->code];
            $currency->rates_updated_at = Carbon::now();
            $currency->save();
            $updated++;
        }

        Cache::forget('active_currencies');

        return $updated;
    }

    /**
     * Returns a list of plain objects: { code, name, symbol }.
     * Plain objects (not Eloquent models) so the cache layer never trips on
     * an Incomplete_Class on deserialize across deploys.
     *
     * @return array<int, object{code: string, name: string, symbol: ?string}>
     */
    public function activeCurrencies(): array
    {
        return Cache::remember('active_currencies', 600, fn () => Currency::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'symbol'])
            ->map(fn ($c) => (object) ['code' => $c->code, 'name' => $c->name, 'symbol' => $c->symbol])
            ->all());
    }

    public function convert(float $usdAmount, string $toCode): float
    {
        $code = strtoupper($toCode);
        if ($code === 'USD') {
            return $usdAmount;
        }
        $currency = Currency::query()->where('code', $code)->first();
        if (! $currency || ! $currency->rate_to_usd || $currency->rate_to_usd <= 0) {
            return $usdAmount;
        }

        return $usdAmount * (float) $currency->rate_to_usd;
    }

    public function format(float $usdAmount, string $toCode): string
    {
        $code = strtoupper($toCode);
        $currency = Currency::query()->where('code', $code)->first();
        $amount = $this->convert($usdAmount, $code);
        $symbol = $currency?->symbol ?: '';
        $decimals = in_array($code, ['JPY', 'KRW', 'LKR', 'KES'], true) ? 0 : 2;

        return $symbol.number_format($amount, $decimals).($symbol === '' ? ' '.$code : '');
    }

    public function userCurrencyCode(): string
    {
        if ($user = auth()->user()) {
            if (! empty($user->preferred_currency)) {
                return strtoupper($user->preferred_currency);
            }
        }
        $cookie = request()->cookie('toco_currency');

        return is_string($cookie) && strlen($cookie) === 3 ? strtoupper($cookie) : 'USD';
    }
}
