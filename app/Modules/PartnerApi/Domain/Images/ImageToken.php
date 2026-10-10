<?php

namespace App\Modules\PartnerApi\Domain\Images;

/**
 * Signed, partner-bound image tokens (SRS §9.2): base64url(payload) "."
 * base64url(HMAC-SHA256). Payload {kid, cid, mid, p, w, exp}. Keys carry a
 * kid so they can be rotated without breaking links already issued (§8.8).
 */
class ImageToken
{
    /**
     * @param  array{cid: int, mid: int, p: string, w: int, exp: int}  $claims
     */
    public static function make(array $claims): string
    {
        $kid = self::currentKid();
        $payload = self::b64(json_encode(['kid' => $kid] + $claims, JSON_UNESCAPED_SLASHES));

        return $payload.'.'.self::b64(hash_hmac('sha256', $payload, self::key($kid), true));
    }

    /**
     * Claims of a valid, unexpired token, or null. Constant-time comparison.
     *
     * @return array{kid: string, cid: int, mid: int, p: string, w: int, exp: int}|null
     */
    public static function parse(string $token): ?array
    {
        if (strlen($token) > 512 || substr_count($token, '.') !== 1) {
            return null;
        }

        [$payload, $signature] = explode('.', $token);
        $claims = json_decode((string) self::unb64($payload), true);

        if (! is_array($claims) || ! isset($claims['kid'], $claims['cid'], $claims['mid'], $claims['p'], $claims['w'], $claims['exp'])) {
            return null;
        }

        $key = self::keys()[$claims['kid']] ?? null;
        if ($key === null || ! hash_equals(self::b64(hash_hmac('sha256', $payload, $key, true)), $signature)) {
            return null;
        }

        return (int) $claims['exp'] >= time() ? $claims : null;
    }

    /** @return array<string, string> kid => secret */
    public static function keys(): array
    {
        $configured = array_filter((array) config('partner_api.images.keys'));

        // No key in .env yet: derive one from APP_KEY so links still sign.
        return $configured !== [] ? $configured : ['d1' => hash_hmac('sha256', 'toco-partner-images', (string) config('app.key'))];
    }

    private static function currentKid(): string
    {
        $kid = (string) config('partner_api.images.current_kid');

        return array_key_exists($kid, self::keys()) ? $kid : (string) array_key_first(self::keys());
    }

    private static function key(string $kid): string
    {
        return self::keys()[$kid];
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $encoded): string|false
    {
        return base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
