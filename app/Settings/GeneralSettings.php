<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $contact_email;

    public ?string $contact_phone;

    /** Comma/newline separated staff alert recipients; empty = contact_email. */
    public ?string $notification_emails;

    public ?string $whatsapp_number;

    public ?string $header_logo;

    public array $footer_logos;

    public bool $show_stock_counts;

    public ?string $google_analytics_id;

    public static function group(): string
    {
        return 'general';
    }
}
