<?php

namespace App\Modules\Mailer\Domain\Importer;

use Carbon\CarbonImmutable;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Throwable;

/**
 * Turns a Gmail API message (format=full) or a raw RFC 822 message (.eml,
 * Rule Tester paste) into a ParsedMessage: headers decoded, base64url /
 * base64 / quoted-printable bodies decoded, text converted to UTF-8.
 */
class MessageParser
{
    public static function fromGmail(Message $message): ParsedMessage
    {
        $payload = $message->getPayload();
        $headers = self::gmailHeaders($payload);
        $text = '';
        $html = '';
        self::walkGmail($payload, $text, $html);

        $received = $message->getInternalDate()
            ? CarbonImmutable::createFromTimestampMs((int) $message->getInternalDate(), 'UTC')
            : self::date($headers['date'] ?? null);

        return new ParsedMessage(
            id: (string) $message->getId(),
            from: self::address($headers['from'] ?? null),
            replyTo: self::address($headers['reply-to'] ?? null),
            subject: $headers['subject'] ?? null,
            receivedAt: $received,
            text: $text,
            html: $html,
        );
    }

    /** Raw message source. Without headers, the whole input is treated as plain text. */
    public static function fromRaw(string $raw, ?string $id = null): ParsedMessage
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$headerBlock, $body] = str_contains($raw, "\n\n") ? explode("\n\n", $raw, 2) : [$raw, ''];
        $headers = self::parseHeaders($headerBlock);

        if (! isset($headers['from']) && ! isset($headers['content-type'])) {
            return new ParsedMessage($id ?? 'pasted', null, null, null, null, trim($raw), '');
        }

        $text = '';
        $html = '';
        self::walkRaw($headers, $body, $text, $html);

        return new ParsedMessage(
            id: $id ?? trim((string) ($headers['message-id'] ?? 'raw-'.sha1($raw)), '<>'),
            from: self::address($headers['from'] ?? null),
            replyTo: self::address($headers['reply-to'] ?? null),
            subject: $headers['subject'] ?? null,
            receivedAt: self::date($headers['date'] ?? null),
            text: $text,
            html: $html,
        );
    }

    /** First email address in a header such as "Name <a@b.com>". */
    public static function address(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }
        if (preg_match('/<([^>]+@[^>]+)>/', $header, $m) || preg_match('/([^\s<>"\',;]+@[^\s<>"\',;]+)/', $header, $m)) {
            return strtolower(trim($m[1]));
        }

        return null;
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/tr|/li|/h\d)\b[^>]*>#i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @return array<string, string> lower-case name => decoded value (first occurrence) */
    protected static function parseHeaders(string $block): array
    {
        $block = preg_replace("/\n[ \t]+/", ' ', $block) ?? $block; // unfold
        $headers = [];
        foreach (explode("\n", $block) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            if (! isset($headers[$name])) {
                $headers[$name] = self::decodeHeader(trim($value));
            }
        }

        return $headers;
    }

    protected static function decodeHeader(string $value): string
    {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }

    /** @param  array<string, string>  $headers */
    protected static function walkRaw(array $headers, string $body, string &$text, string &$html): void
    {
        $type = strtolower($headers['content-type'] ?? 'text/plain');

        if (str_starts_with($type, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $headers['content-type'], $b)) {
            $parts = explode('--'.$b[1], $body);
            array_shift($parts); // preamble
            foreach ($parts as $part) {
                if (str_starts_with($part, '--')) {
                    break; // closing delimiter
                }
                $part = ltrim($part, "\n");
                [$h, $content] = str_contains($part, "\n\n") ? explode("\n\n", $part, 2) : [$part, ''];
                self::walkRaw(self::parseHeaders($h), $content, $text, $html);
            }

            return;
        }

        if (str_starts_with($type, 'message/rfc822')) {
            [$h, $content] = str_contains($body, "\n\n") ? explode("\n\n", $body, 2) : [$body, ''];
            self::walkRaw(self::parseHeaders($h), $content, $text, $html);

            return;
        }

        $disposition = strtolower($headers['content-disposition'] ?? '');
        if (str_starts_with($disposition, 'attachment')) {
            return; // attachments are ignored and never stored
        }

        $content = match (strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $content = self::toUtf8($content, self::charset($type));

        if (str_starts_with($type, 'text/html')) {
            $html .= $content;
        } elseif (str_starts_with($type, 'text/')) {
            $text .= ($text === '' ? '' : "\n").$content;
        }
    }

    protected static function walkGmail(?MessagePart $part, string &$text, string &$html): void
    {
        if ($part === null) {
            return;
        }

        $type = strtolower((string) $part->getMimeType());
        foreach ($part->getParts() ?? [] as $child) {
            self::walkGmail($child, $text, $html);
        }

        if ($part->getFilename() || ! str_starts_with($type, 'text/')) {
            return;
        }

        $data = $part->getBody()?->getData();
        if (! $data) {
            return;
        }

        $headers = self::gmailHeaders($part);
        $content = self::toUtf8((string) base64_decode(strtr($data, '-_', '+/')), self::charset($headers['content-type'] ?? $type));

        if ($type === 'text/html') {
            $html .= $content;
        } elseif ($type === 'text/plain') {
            $text .= ($text === '' ? '' : "\n").$content;
        }
    }

    /** @return array<string, string> */
    protected static function gmailHeaders(?MessagePart $part): array
    {
        $out = [];
        foreach ($part?->getHeaders() ?? [] as $h) {
            $name = strtolower((string) $h->getName());
            $out[$name] ??= (string) $h->getValue();
        }

        return $out;
    }

    protected static function charset(string $contentType): string
    {
        return preg_match('/charset="?([^";\s]+)"?/i', $contentType, $m) ? strtoupper($m[1]) : 'UTF-8';
    }

    protected static function toUtf8(string $content, string $charset): string
    {
        if ($charset === 'UTF-8' || $charset === 'US-ASCII') {
            return $content;
        }

        try {
            return mb_convert_encoding($content, 'UTF-8', $charset);
        } catch (Throwable) {
            return $content;
        }
    }

    protected static function date(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
