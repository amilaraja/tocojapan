<?php

namespace App\Modules\Mailer\Support;

use App\Modules\Mailer\Models\MailerSetting;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Key/value settings stored in mailer_settings (SRS 8.1).
 *
 * Values are JSON-encoded so lists (domains, nav links) round-trip. Keys in
 * SECRETS are encrypted at rest with the app key (TOC-NFR-001) and are never
 * returned to the UI in full; use masked().
 */
class MailerSettings
{
    /** @var list<string> */
    public const SECRETS = ['brevo_api_key'];

    /** @var array<string, mixed>|null */
    protected ?array $loaded = null;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $general = rescue(fn () => app(GeneralSettings::class), null, false);

        return [
            'import_interval_minutes' => (int) config('mailer.import.default_interval_minutes', 15),
            'mailbox' => config('mailer.google.mailbox'),
            'google_key_path' => config('mailer.google.sa_key_path'),
            'own_domains' => ['tocojapan.com'],
            'brevo_api_key' => null,

            // Email template content (TOC-TPL-009): editable, never hard-coded.
            'logo_path' => null,
            'top_bar_text' => 'Japanese used vehicles for export',
            'nav_links' => [
                ['label' => 'STOCK LIST', 'url' => 'https://tocojapan.com/vehicles'],
                ['label' => 'HOW TO BUY', 'url' => 'https://tocojapan.com/how-to-buy-cars-and-other-vehicles'],
                ['label' => 'CONTACT', 'url' => 'https://tocojapan.com/contact'],
            ],
            'cta_heading' => 'Looking for something specific?',
            'cta_text' => 'Tell us the make, model and budget. We search the Japanese auctions for you.',
            'cta_button' => 'SEND A REQUEST',
            'cta_url' => 'https://tocojapan.com/contact',
            'fraud_text' => 'Beware of fraudsters. Always verify our company bank details before sending any payment.',
            'footer_company' => 'TOCO INTERNATIONAL',
            'footer_address' => '3400-1 Horigome-Cho, Sano City, Tochigi 327-0843, Japan',
            'footer_phone' => $general?->contact_phone,
            'footer_whatsapp' => $general?->whatsapp_number,
            'footer_email' => $general?->contact_email ?: 'info@tocojapan.com',
            'footer_reason' => 'You are receiving this email because you contacted TOCO International about a vehicle.',
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    /** Stored values over defaults. Secrets are included decrypted: server-side use only. */
    public function all(): array
    {
        if ($this->loaded === null) {
            $stored = [];
            foreach (MailerSetting::query()->get() as $row) {
                $stored[$row->key] = $this->decode($row);
            }
            $this->loaded = $stored;
        }

        return array_merge(static::defaults(), array_filter($this->loaded, fn ($v) => $v !== null));
    }

    public function set(string $key, mixed $value): void
    {
        $secret = in_array($key, self::SECRETS, true);
        $json = $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        MailerSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $secret && $json !== null ? Crypt::encryptString($json) : $json, 'encrypted' => $secret],
        );

        if ($this->loaded !== null) {
            $this->loaded[$key] = $value;
        }
    }

    /** @param  array<string, mixed>  $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /** Brevo key: saved setting first, then MAILER_BREVO_API_KEY. */
    public function brevoApiKey(): ?string
    {
        return $this->get('brevo_api_key') ?: (config('mailer.brevo.api_key') ?: null);
    }

    /** "••••1234" style display for a secret, or null when not set (TOC-NFR-001). */
    public function masked(string $key): ?string
    {
        $value = $key === 'brevo_api_key' ? $this->brevoApiKey() : $this->get($key);

        return filled($value) ? '••••'.mb_substr((string) $value, -4) : null;
    }

    protected function decode(MailerSetting $row): mixed
    {
        if ($row->value === null) {
            return null;
        }

        try {
            $json = $row->encrypted ? Crypt::decryptString($row->value) : $row->value;
        } catch (Throwable) {
            // Unreadable after an APP_KEY change: treat as unset, never crash the admin.
            return null;
        }

        return json_decode($json, true);
    }
}
