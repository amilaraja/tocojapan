<?php

namespace App\Modules\PartnerApi\Domain\Webhooks;

use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerWebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Delivers change notifications (FR-SYNC-02, §8.5): HTTPS only, public
 * addresses only (no SSRF into Toco's network), no redirects, signed with
 * HMAC-SHA256 over "timestamp.body", retried with backoff for 24 hours.
 */
class WebhookSender
{
    /** Minutes to wait after each failed attempt; the last value repeats. */
    public const BACKOFF = [1, 2, 5, 10, 30, 60, 120, 240];

    public const GIVE_UP_HOURS = 24;

    public const TIMEOUT_SECONDS = 10;

    /** Send what is due, for at most $seconds. Returns how many were attempted. */
    public function sendDue(int $seconds = 40, int $limit = 200): int
    {
        $deadline = microtime(true) + $seconds;
        $count = 0;

        $due = PartnerWebhookDelivery::with('partner')
            ->where('status', PartnerWebhookDelivery::PENDING)
            ->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')->limit($limit)->get();

        foreach ($due as $delivery) {
            if (microtime(true) > $deadline) {
                break;
            }
            $this->attempt($delivery);
            $count++;
        }

        return $count;
    }

    public function attempt(PartnerWebhookDelivery $delivery): void
    {
        $partner = $delivery->partner;

        // A partner that lost access or its address gets nothing more.
        if (! $partner || ! $partner->webhook_url || $partner->accessDenialCode() !== null) {
            $delivery->forceFill(['status' => PartnerWebhookDelivery::FAILED, 'last_error' => 'Partner not active or no address'])->save();

            return;
        }

        [$ok, $code, $error] = $this->post($partner, $delivery->event, $delivery->delivery_id, $delivery->payload);
        $attempts = $delivery->attempts + 1;

        if ($ok) {
            $delivery->forceFill(['status' => PartnerWebhookDelivery::DELIVERED, 'attempts' => $attempts, 'last_status_code' => $code,
                'last_error' => null, 'delivered_at' => now(), 'next_attempt_at' => null])->save();

            return;
        }

        $wait = self::BACKOFF[min($attempts - 1, count(self::BACKOFF) - 1)];
        $next = now()->addMinutes($wait);
        $giveUp = $delivery->created_at->copy()->addHours(self::GIVE_UP_HOURS);

        $delivery->forceFill([
            'attempts' => $attempts,
            'last_status_code' => $code,
            'last_error' => Str::limit((string) $error, 250),
            'status' => $next->greaterThan($giveUp) ? PartnerWebhookDelivery::FAILED : PartnerWebhookDelivery::PENDING,
            'next_attempt_at' => $next->greaterThan($giveUp) ? null : $next,
        ])->save();
    }

    /** "Send test event" in the admin: synchronous, nothing stored. @return array{0: bool, 1: int|null, 2: string|null} */
    public function ping(Partner $partner): array
    {
        $id = 'whd_test_'.strtolower((string) Str::ulid());

        return $this->post($partner, WebhookOutbox::PING, $id, [
            'id' => $id, 'type' => WebhookOutbox::PING, 'created_at' => now()->toIso8601ZuluString(),
            'data' => ['message' => 'Test notification from Toco. No action needed.'],
        ]);
    }

    /** Header value: t=<unix>,v1=<hex hmac of "t.body">. */
    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: bool, 1: int|null, 2: string|null}
     */
    private function post(Partner $partner, string $event, string $deliveryId, array $payload): array
    {
        $url = (string) $partner->webhook_url;
        $host = parse_url($url, PHP_URL_HOST);

        if (! str_starts_with($url, 'https://') || ! is_string($host)) {
            return [false, null, 'Address must start with https://'];
        }

        $ip = self::publicAddress($host);
        if ($ip === null) {
            return [false, null, 'Address does not resolve to a public server'];
        }

        $secret = $partner->ensureWebhookSecret();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->withoutRedirecting()
                // Pin the checked address so DNS cannot switch to an internal one mid-request.
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]]])
                ->withHeaders([
                    'User-Agent' => 'Toco-Webhooks/1.0',
                    'X-Toco-Event' => $event,
                    'X-Toco-Delivery' => $deliveryId,
                    'X-Toco-Timestamp' => (string) $timestamp,
                    'X-Toco-Signature' => self::signature($secret, $timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            return [false, null, 'Connection failed: '.Str::limit($e->getMessage(), 180)];
        }

        return $response->successful()
            ? [true, $response->status(), null]
            : [false, $response->status(), 'Partner answered HTTP '.$response->status()];
    }

    /** First public IP of the host, or null if it has none or any private/reserved one. */
    public static function publicAddress(string $host): ?string
    {
        $fake = app()->runningUnitTests() ? config('partner_api.webhooks.fake_resolve') : null;
        $ips = match (true) {
            is_array($fake) => isset($fake[$host]) ? [$fake[$host]] : [],
            filter_var($host, FILTER_VALIDATE_IP) !== false => [$host],
            default => gethostbynamel($host) ?: [],
        };
        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        return $ips[0];
    }
}
