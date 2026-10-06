<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Sends website alerts (contact form, quote requests, spare parts, proforma
 * invoices) to the staff inboxes in Site settings → General.
 *
 * Never throws: a mail problem must not break the customer's submission.
 * Every attempt is logged so "did the site send it?" can be answered.
 */
class StaffMail
{
    /** @return list<string> */
    public static function recipients(): array
    {
        $settings = app(GeneralSettings::class);
        $list = preg_split('/[\s,;]+/', (string) ($settings->notification_emails ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $list = array_values(array_unique(array_filter($list, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));

        return $list ?: array_values(array_filter([$settings->contact_email ?: config('mail.from.address')]));
    }

    public static function send(Notification $notification, string $what): void
    {
        $to = self::recipients();
        try {
            Notifier::route('mail', $to)->notify($notification);
            Log::info("Staff email sent: {$what}", ['to' => $to]);
        } catch (\Throwable $e) {
            Log::error("Staff email FAILED: {$what}", ['to' => $to, 'error' => $e->getMessage()]);
        }
    }
}
