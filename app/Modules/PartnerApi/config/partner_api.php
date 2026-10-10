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

    'hold_hours_min' => 24,
    'hold_hours_max' => 96,
];
