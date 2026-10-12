<?php

namespace App\Modules\Crm\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CRM settings (CRM-GEN-004): key/value in crm_settings, with the proposed
 * defaults from the build plan when a key was never saved. Times are minutes
 * of business time unless the key says days.
 */
class CrmSettings
{
    public const DEFAULTS = [
        // Business hours, Asia/Tokyo. ISO weekday 1 = Monday.
        'calendar.timezone' => 'Asia/Tokyo',
        'calendar.hours' => [1 => ['09:00', '18:00'], 2 => ['09:00', '18:00'], 3 => ['09:00', '18:00'], 4 => ['09:00', '18:00'], 5 => ['09:00', '18:00']],

        // Service levels (SRS §7.1).
        'sla.first_response_minutes' => 120,
        'sla.customer_reply_minutes' => 540,
        'sla.quote_sent_minutes' => 540,
        'sla.payment_due_days' => 3,
        'sla.vessel_booked_days' => 10,
        'sla.documents_couriered_days' => 5,
        'sla.warning_percent' => 75,
        'sla.stale_days' => 3,
        'sla.stale_admin_days' => 5,
        'sla.unassigned_escalation_minutes' => 30,
        'sla.default_follow_up_days' => 2,

        // Customer emails (OPEN-05, OPEN-12).
        // Buyers know staff by nickname only (13 Oct 2026); no nickname = the team name.
        'email.sender_name' => 'TOCO Sales Team',
        'email.show_nickname' => true,
        'email.review_url' => null,
        'email.review_delay_days' => 3,
        'email.payment_reminder_days_before' => 1,

        // Buyer replies from the mailbox (plan P7).
        'inbound.enabled' => true,

        // Daily digest time, Asia/Tokyo.
        'digest.time' => '08:30',

        // Extra inboxes for admin alerts (unassigned Cases, breaches), so they keep
        // reaching the address the old "Staff notification emails" used.
        'alerts.extra_admin_emails' => null,

        // CRM customer emails replace the old order-status emails (13 Oct 2026).
        'email.replace_order_emails' => true,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $stored = Cache::remember('crm:settings', 300, fn () => DB::table('crm_settings')->pluck('value', 'key')
            ->map(fn ($value) => json_decode((string) $value, true))
            ->all());

        return array_replace(self::DEFAULTS, $stored);
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('crm_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget('crm:settings');
    }

    /** @param array<string, mixed> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }
}
