<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SocialSettings extends Settings
{
    public bool $facebook_enabled;

    public ?string $facebook_page_id;

    /** Encrypted at rest via SettingsMigration::addEncrypted(). */
    public ?string $facebook_page_access_token;

    public string $facebook_post_template;

    public static function group(): string
    {
        return 'social';
    }

    public static function encrypted(): array
    {
        return ['facebook_page_access_token'];
    }
}
