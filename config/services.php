<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Cloudflare Turnstile — server-side CAPTCHA on guest auth forms.
    // Site key is rendered into the widget; secret_key is used by
    // App\Rules\Turnstile to call siteverify. Both blank ⇒ rule skipped
    // (so local dev without Cloudflare set up still works).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'google' => [
        // Google Search Console API integration for the admin dashboard.
        //   site_url: the verified property, e.g. "sc-domain:tocojapan.com" or
        //     "https://tocojapan.com/"
        //   credentials_path: relative to storage_path(). Drop the service
        //     account JSON at storage/app/google-credentials.json by default.
        'search_console' => [
            'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
            'credentials_path' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS', 'app/google-credentials.json'),
        ],
    ],

];
