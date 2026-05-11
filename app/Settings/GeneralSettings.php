<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $contact_email;

    public ?string $contact_phone;

    public ?string $whatsapp_number;

    public array $footer_logos;

    public static function group(): string
    {
        return 'general';
    }
}
