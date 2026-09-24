<?php

namespace App\Modules\Mailer\Domain\Importer;

use Carbon\CarbonImmutable;

/**
 * A mailbox message in memory only. Bodies are never persisted (TOC-LOG-003);
 * only id, sender, received time and extracted fields are stored.
 */
final class ParsedMessage
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $from,
        public readonly ?string $replyTo,
        public readonly ?string $subject,
        public readonly ?CarbonImmutable $receivedAt,
        public readonly string $text,
        public readonly string $html,
    ) {}

    /** Plain text, or the HTML reduced to text when there is no text part. */
    public function bodyText(): string
    {
        if (trim($this->text) !== '') {
            return $this->text;
        }

        return MessageParser::htmlToText($this->html);
    }
}
