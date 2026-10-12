<?php

// TOCO CRM (SRS-TOC-02). Business settings (hours, SLAs, emails) live in
// crm_settings and are edited in CRM → Settings; this file holds only
// deployment values.

return [
    // Intake, routes, schedule and menus. Off only until the admin screens
    // exist (build day 1); then on for good ("live as each part lands").
    'enabled' => (bool) env('CRM_ENABLED', false),

    // Morning summary time, Asia/Tokyo (SRS §8.1).
    'digest_time' => env('CRM_DIGEST_TIME', '08:30'),

    // Payment slips and documents: largest upload, in kilobytes.
    'max_upload_kb' => 10240,
];
