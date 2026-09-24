<?php

namespace App\Modules\Mailer\Domain\Importer;

use App\Modules\Mailer\Models\ApprovedSender;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Approved sender matching (TOC-EXT-001, TOC-IMP-004) and the Gmail search query. */
class SenderMatcher
{
    /** @param  Collection<int, ApprovedSender>  $senders */
    public function match(?string $from, Collection $senders): ?ApprovedSender
    {
        if (! $from) {
            return null;
        }
        $from = strtolower($from);
        $domain = substr($from, (int) strrpos($from, '@') + 1);

        return $senders->first(function (ApprovedSender $s) use ($from, $domain) {
            $value = strtolower(trim($s->match_value));

            return $s->match_type === ApprovedSender::MATCH_DOMAIN
                ? ltrim($value, '@') === $domain
                : $value === $from;
        });
    }

    /** from:(a@x.com OR @portal.com) after:1727136000 */
    public function query(Collection $senders, ?CarbonInterface $after = null): string
    {
        $terms = $senders->map(fn (ApprovedSender $s) => $s->match_type === ApprovedSender::MATCH_DOMAIN
            ? '@'.ltrim(strtolower($s->match_value), '@')
            : strtolower($s->match_value))->unique()->implode(' OR ');

        return trim('from:('.$terms.')'.($after ? ' after:'.$after->getTimestamp() : ''));
    }

    /** "@portal.com" → domain, "leads@portal.com" → address, else null. */
    public static function typeOf(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^@[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $value)) {
            return ApprovedSender::MATCH_DOMAIN;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? ApprovedSender::MATCH_ADDRESS : null;
    }
}
