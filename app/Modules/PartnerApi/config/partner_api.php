<?php

// TOCO Partner Stock API (SRS-TOC-PAPI). Requirement IDs in comments expand
// to TOC-PAPI-<ID>.

return [
    // Off until go-live: every partner route answers 404 while false.
    'enabled' => (bool) env('PARTNER_API_ENABLED', false),

    // Bind the routes to a dedicated host (api.tocojapan.com) once its DNS
    // and SSL exist. Null = served on the main site under the prefix.
    'host' => env('PARTNER_API_HOST'),
    'prefix' => 'partner/v1',

    // Contract dates and day boundaries are Toco's business days.
    'timezone' => env('PARTNER_API_TIMEZONE', 'Asia/Tokyo'),

    'auth' => [
        'token_ttl_seconds' => 900, // §8.1: 15-minute bearer tokens
        'secret_overlap_days' => 7, // FR-PRT-02: old secret keeps working after rotation
        'token_attempts_per_minute' => 30, // failed token requests per IP
    ],

    'request' => [
        'max_body_bytes' => 64 * 1024, // §8.4
    ],

    // Defaults for a new partner (SRS §4, UI brief 2.3).
    'defaults' => [
        'rate_per_minute' => 120,
        'rate_per_day' => 20000,
        'image_daily_limit' => 50000,
        'image_max_width' => 1280,
        'max_active_holds' => 5,
        'hold_hours' => 48,
    ],

    'images' => [
        // kid => secret. PARTNER_API_IMAGE_KEYS="k2:secret2,k1:secret1"; the first
        // (or PARTNER_API_IMAGE_KID) signs new links, the rest still verify.
        'keys' => collect(explode(',', (string) env('PARTNER_API_IMAGE_KEYS', '')))
            ->filter(fn ($pair) => str_contains($pair, ':'))
            ->mapWithKeys(fn ($pair) => [trim(strstr($pair, ':', true)) => trim(substr(strstr($pair, ':'), 1))])
            ->all(),
        'current_kid' => env('PARTNER_API_IMAGE_KID'),
        'variant_disk_path' => 'private/partner-variants',
        'master_disk_path' => 'private/media-masters', // FR-IMG-01
    ],

    'hold_hours_min' => 24,
    'hold_hours_max' => 96,
];
