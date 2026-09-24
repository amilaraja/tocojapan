<?php

namespace App\Modules\Mailer\Domain\Importer;

/**
 * Finds candidate addresses in a message (TOC-EXT-002) and normalises
 * them (TOC-EXT-003). Order: plain text, HTML (mailto: links and visible
 * text), then Reply-To when enabled. Duplicates are removed.
 */
class AddressExtractor
{
    protected const PATTERN = '/[a-z0-9._%+\'-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}/i';

    /** @return list<string> */
    public function extract(ParsedMessage $message, bool $useReplyTo = false): array
    {
        $found = [];

        preg_match_all(self::PATTERN, $message->text, $m);
        array_push($found, ...$m[0]);

        if ($message->html !== '') {
            preg_match_all('/mailto:([^"\'?>\s]+)/i', $message->html, $mailto);
            foreach ($mailto[1] as $addr) {
                $found[] = rawurldecode(html_entity_decode($addr));
            }
            preg_match_all(self::PATTERN, MessageParser::htmlToText($message->html), $m);
            array_push($found, ...$m[0]);
        }

        if ($useReplyTo && $message->replyTo) {
            $found[] = $message->replyTo;
        }

        $out = [];
        foreach ($found as $raw) {
            $email = self::normalise($raw);
            if ($email !== '' && ! in_array($email, $out, true)) {
                $out[] = $email;
            }
        }

        return $out;
    }

    /** '  <John.Doe@Example.COM>, ' → 'john.doe@example.com' */
    public static function normalise(string $raw): string
    {
        $email = trim($raw);
        $email = trim($email, " \t\n\r\0\x0B<>()[]{}\"',;:.!?");

        return mb_strtolower($email);
    }
}
