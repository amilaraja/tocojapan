<?php

namespace Tests\Mailer\Support;

use App\Modules\Mailer\Domain\Importer\AddressValidator;
use App\Modules\Mailer\Domain\Importer\MailboxReader;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Support\Sleep;

/** Shared setup for Mailer tests (no live DNS, Gmail or Brevo). */
final class MailerTest
{
    /** Domains ending in .invalid have no mail server; everything else does. */
    public static function fakeDns(): void
    {
        app()->instance(AddressValidator::class, (new AddressValidator)->resolveWith(fn (string $d) => ! str_ends_with($d, '.invalid')));
    }

    public static function brevoKey(string $key = 'xkeysib-test-0000'): void
    {
        app(MailerSettings::class)->set('brevo_api_key', $key);
        Sleep::fake(syncWithCarbon: true);
    }

    public static function mailbox(): FakeMailbox
    {
        $box = new FakeMailbox;
        app()->instance(MailboxReader::class, $box);

        return $box;
    }

    public static function tocoSender(array $over = []): ApprovedSender
    {
        return ApprovedSender::create(array_merge([
            'label' => 'Website inquiry form',
            'match_value' => 'info@tocojapan.com',
            'match_type' => ApprovedSender::MATCH_ADDRESS,
            'active' => true,
            'brevo_list_ids' => [7],
            'consent_mode' => ApprovedSender::CONSENT_DIRECT,
            'max_per_message' => 3,
            'field_rules' => [
                ['field' => 'name', 'pattern' => '^From: (.+?) <'],
                ['field' => 'phone', 'pattern' => '^Phone: (.+)$'],
                ['field' => 'stock_ref', 'pattern' => '\b(E\d{5})\b'],
            ],
        ], $over));
    }

    public static function portalSender(array $over = []): ApprovedSender
    {
        return ApprovedSender::create(array_merge([
            'label' => 'Example portal',
            'match_value' => '@example-portal.com',
            'match_type' => ApprovedSender::MATCH_DOMAIN,
            'active' => true,
            'brevo_list_ids' => [8],
            'consent_mode' => ApprovedSender::CONSENT_DIRECT,
            'max_per_message' => 3,
            'field_rules' => [
                ['field' => 'name', 'pattern' => '^Name: (.+)$'],
                ['field' => 'country', 'pattern' => '^Country: (.+)$'],
            ],
        ], $over));
    }
}
