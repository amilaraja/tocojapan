<?php

// TOCO Mailer module config. Secrets can also be saved (encrypted) in
// mailer_settings from the Mailer Settings screen; those win over .env.

return [
    'display_timezone' => env('MAILER_DISPLAY_TIMEZONE', 'Asia/Tokyo'),

    // Queue that import / push / stats jobs run on (worker: routes/console.php).
    'queue' => 'mailer',

    'google' => [
        'sa_key_path' => env('MAILER_GOOGLE_SA_KEY_PATH'),
        'mailbox' => env('MAILER_GMAIL_MAILBOX'),
        // TOC-IMP-001: the only scope the importer may ever request.
        'scope' => 'https://www.googleapis.com/auth/gmail.readonly',
        'timeout' => 20, // TOC-NFR-005
    ],

    'brevo' => [
        'api_key' => env('MAILER_BREVO_API_KEY'),
        'base_url' => env('MAILER_BREVO_BASE_URL', 'https://api.brevo.com/v3'),
        'timeout' => 20, // TOC-NFR-005
        'max_retries' => 5, // TOC-BRV-005
    ],

    'import' => [
        'default_interval_minutes' => 15, // TOC-IMP-002
        'min_interval_minutes' => 5,
        'max_interval_minutes' => 1440,
        'backfill_batch_size' => 100, // TOC-IMP-008
        'alert_after_failures' => 3, // TOC-IMP-009
        'default_max_per_message' => 3, // TOC-EXT-007
    ],

    // TOC-LOG-004
    'retention_months' => 12,

    'campaign' => [
        'min_vehicles' => 2,
        'max_vehicles' => 12,
    ],
];
