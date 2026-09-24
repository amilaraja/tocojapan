<?php

namespace App\Modules\Mailer\Domain\Brevo;

/**
 * Hard rule 1 (TOC-CMP-002) and hard rule 5 (TOC-BRV-002).
 *
 * Checked by BrevoClient before every request. TOCO Mailer may create and
 * update DRAFT campaigns and contacts, but must never:
 *  - send or test-send a campaign, send transactional email/SMS/WhatsApp,
 *    or change a campaign's status;
 *  - schedule anything (any scheduledAt, at any depth of the payload);
 *  - change a contact's blacklisted (unsubscribed) flag.
 */
final class BrevoGuard
{
    /** @var list<string> */
    private const FORBIDDEN_PATHS = [
        '#^/emailcampaigns/[^/]+/(sendnow|sendtest|sendreport|status)$#',
        '#^/smtp/email$#',
        '#^/smtp/templates/[^/]+/sendtest$#',
        '#^/smscampaigns(/|$)#',
        '#^/transactionalsms(/|$)#',
        '#^/whatsappcampaigns(/|$)#',
        '#^/whatsapp(/|$)#',
    ];

    /** @var list<string> Payload keys that are never allowed, at any depth. */
    private const FORBIDDEN_KEYS = ['scheduledat', 'emailblacklisted', 'unlinklistids'];

    /** @param  array<mixed>  $payload */
    public static function assertAllowed(string $method, string $path, array $payload = []): void
    {
        $normalised = '/'.trim(strtolower((string) (parse_url($path, PHP_URL_PATH) ?? $path)), '/');

        foreach (self::FORBIDDEN_PATHS as $pattern) {
            if (preg_match($pattern, $normalised)) {
                throw new BrevoSendForbidden('TOCO Mailer never sends or schedules email (TOC-CMP-002): '.strtoupper($method).' '.$path);
            }
        }

        if ($key = self::findForbiddenKey($payload)) {
            throw new BrevoSendForbidden("TOCO Mailer never sets \"{$key}\" (TOC-CMP-002 / TOC-BRV-002).");
        }
    }

    /** @param  array<mixed>  $payload */
    private static function findForbiddenKey(array $payload): ?string
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                return $key;
            }
            if (is_array($value) && ($found = self::findForbiddenKey($value))) {
                return $found;
            }
        }

        return null;
    }
}
