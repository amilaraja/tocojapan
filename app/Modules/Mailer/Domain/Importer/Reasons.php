<?php

namespace App\Modules\Mailer\Domain\Importer;

/** Plain-English labels for outcomes and reasons (TOC-GEN-007). */
final class Reasons
{
    public const LABELS = [
        'added' => 'Added',
        'updated' => 'Updated',
        'skipped' => 'Skipped',
        'failed' => 'Failed',
        'unsubscribed' => 'Unsubscribed earlier',
        'bounced' => 'Email address bounced',
        'ignored' => 'On your ignore list',
        'no_mail_domain' => "Email domain doesn't exist",
        'invalid_syntax' => 'Not a valid email address',
        'over_limit' => 'Over the limit for one message',
        'own_domain' => 'TOCO address',
        'sender_address' => "The sender's own address",
        'system_address' => 'Automatic address (no-reply and similar)',
        'brevo_error' => 'Brevo refused it',
        'confirmation_sent' => 'Asked to confirm by email',
    ];

    public static function label(?string $code): string
    {
        return $code === null ? '' : (self::LABELS[$code] ?? ucfirst(str_replace('_', ' ', $code)));
    }
}
