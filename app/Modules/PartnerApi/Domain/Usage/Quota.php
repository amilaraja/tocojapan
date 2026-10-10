<?php

namespace App\Modules\PartnerApi\Domain\Usage;

use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerClient;
use App\Modules\PartnerApi\Support\PartnerApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate limits and daily quotas (SRS §8.3): per client per minute, per
 * partner per day for API calls, and a separate daily image allowance.
 * Days run on Toco's business day (partner_api.timezone).
 */
class Quota
{
    public static function enforceMinute(PartnerClient $client, Partner $partner): array
    {
        $key = 'partner-api:minute:'.$client->id;
        $limit = max(1, (int) $partner->rate_per_minute);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retry = RateLimiter::availableIn($key);
            throw new PartnerApiException(429, 'too_many_requests', 'Too many requests this minute.',
                "Your limit is {$limit} requests per minute.", self::headers($limit, 0, $retry) + ['Retry-After' => (string) $retry]);
        }

        $hits = RateLimiter::hit($key, 60);

        return self::headers($limit, max(0, $limit - $hits), RateLimiter::availableIn($key));
    }

    public static function ensureDaily(Partner $partner, string $kind): void
    {
        $limit = self::dailyLimit($partner, $kind);

        if (self::usedToday($partner, $kind) >= $limit) {
            $retry = self::secondsToReset();
            throw new PartnerApiException(429, $kind === 'image' ? 'image_limit_reached' : 'daily_limit_reached',
                $kind === 'image' ? 'Daily photo allowance used up.' : 'Daily request limit reached.',
                'The allowance resets at midnight Japan time.', ['Retry-After' => (string) $retry]);
        }
    }

    public static function countDaily(Partner $partner, string $kind): int
    {
        return RateLimiter::hit(self::dayKey($partner, $kind), self::secondsToReset());
    }

    public static function usedToday(Partner $partner, string $kind): int
    {
        return RateLimiter::attempts(self::dayKey($partner, $kind));
    }

    public static function dailyLimit(Partner $partner, string $kind): int
    {
        return (int) ($kind === 'image' ? $partner->image_daily_limit : $partner->rate_per_day);
    }

    public static function secondsToReset(): int
    {
        $now = CarbonImmutable::now(config('partner_api.timezone'));

        return max(1, (int) $now->diffInSeconds($now->addDay()->startOfDay(), true));
    }

    private static function dayKey(Partner $partner, string $kind): string
    {
        return 'partner-api:day:'.$kind.':'.$partner->id.':'.CarbonImmutable::now(config('partner_api.timezone'))->format('Ymd');
    }

    /** @return array<string, string> RateLimit-* headers (IETF draft) for the minute window. */
    private static function headers(int $limit, int $remaining, int $reset): array
    {
        return [
            'RateLimit-Limit' => (string) $limit,
            'RateLimit-Remaining' => (string) $remaining,
            'RateLimit-Reset' => (string) max(0, $reset),
        ];
    }
}
