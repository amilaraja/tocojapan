<?php

namespace App\Modules\PartnerApi\Domain\Auth;

/**
 * Client ids, secrets and access tokens (§8.1, FR-PRT-02).
 * Secrets are 256-bit random and stored as Argon2id hashes; access tokens
 * are 256-bit random and stored as SHA-256 (fast lookup, nothing to brute-force).
 */
class ClientSecret
{
    /** Shown in the admin key table, e.g. "tcp_live_a1b2". */
    public const PREFIX_LENGTH = 13;

    public static function generate(string $environment): string
    {
        return 'tcp_'.$environment.'_'.self::random();
    }

    public static function generateClientId(): string
    {
        return 'tcc_'.bin2hex(random_bytes(10));
    }

    public static function generateAccessToken(): string
    {
        return 'tcat_'.self::random();
    }

    public static function prefix(string $secret): string
    {
        return substr($secret, 0, self::PREFIX_LENGTH);
    }

    public static function hash(string $secret): string
    {
        return password_hash($secret, PASSWORD_ARGON2ID);
    }

    public static function verify(string $secret, string $hash): bool
    {
        return password_verify($secret, $hash);
    }

    /** 32 random bytes, base64url without padding (43 characters). */
    private static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
