<?php

namespace App\Modules\Mailer\Domain\Brevo;

/**
 * Brevo answered 401: the key is wrong, or (common) this server's IP is not
 * in Brevo's "Authorised IPs". Extends BrevoUnavailable so an import run
 * fails and rolls back (nothing is marked failed per contact) and a push is
 * refused with this readable message.
 */
class BrevoRejected extends BrevoUnavailable
{
    public static function fromMessage(?string $brevoMessage): self
    {
        if ($brevoMessage && preg_match('/unrecognised IP address (\d{1,3}(?:\.\d{1,3}){3}|[0-9a-f]*:[0-9a-f:]+)/i', $brevoMessage, $m)) {
            return new self("Brevo blocked this server's address ({$m[1]}). In Brevo, open Security, Authorised IPs and add {$m[1]}, then try again.", 401);
        }

        return new self('Brevo did not accept the key. Paste the key again in Mailer settings.', 401);
    }
}
