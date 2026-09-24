<?php

namespace App\Modules\Mailer\Domain\Importer;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * TOC-EXT-005: syntax check, then MX (or A when no MX) record for the
 * domain. DNS answers are cached for 24 hours.
 */
class AddressValidator
{
    /** @var Closure(string): bool|null */
    protected ?Closure $resolver = null;

    /** Swap the DNS lookup (tests). */
    public function resolveWith(Closure $resolver): static
    {
        $this->resolver = $resolver;

        return $this;
    }

    /** Reason code when invalid, null when the address is usable. */
    public function problem(string $email): ?string
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'invalid_syntax';
        }

        $domain = substr($email, strrpos($email, '@') + 1);

        return $this->domainAcceptsMail($domain) ? null : 'no_mail_domain';
    }

    public function domainAcceptsMail(string $domain): bool
    {
        return (bool) Cache::remember('mailer:dns:'.$domain, 86400, function () use ($domain): bool {
            if ($this->resolver) {
                return ($this->resolver)($domain);
            }

            $fqdn = rtrim($domain, '.').'.';

            return @checkdnsrr($fqdn, 'MX') || @checkdnsrr($fqdn, 'A');
        });
    }
}
