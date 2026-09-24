<?php

namespace Tests\Mailer\Support;

use App\Modules\Mailer\Domain\Importer\HistoryExpired;
use App\Modules\Mailer\Domain\Importer\MailboxReader;
use App\Modules\Mailer\Domain\Importer\MailboxUnavailable;
use App\Modules\Mailer\Domain\Importer\MessageParser;
use App\Modules\Mailer\Domain\Importer\ParsedMessage;

/** In-memory mailbox fed from .eml fixtures; understands the from:(…) after:N query. */
class FakeMailbox implements MailboxReader
{
    /** @var array<string, ParsedMessage> */
    public array $messages = [];

    public ?string $historyId = '1000';

    /** @var list<string>|null ids returned by idsSinceHistory; null = history expired */
    public ?array $historyIds = null;

    public int $failOnFetchNumber = 0;

    public bool $down = false;

    public int $fetches = 0;

    /** @var list<string> */
    public array $queries = [];

    public static function fixture(string $path): string
    {
        return (string) file_get_contents(base_path('tests/Mailer/Fixtures/eml/'.$path));
    }

    public function addEml(string $raw, string $id): ParsedMessage
    {
        return $this->messages[$id] = MessageParser::fromRaw($raw, $id);
    }

    public function add(ParsedMessage $message): void
    {
        $this->messages[$message->id] = $message;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function currentHistoryId(): ?string
    {
        $this->guard();

        return $this->historyId;
    }

    public function idsSinceHistory(string $historyId): array
    {
        $this->guard();
        if ($this->historyIds === null) {
            throw new HistoryExpired('expired');
        }

        return $this->historyIds;
    }

    public function search(string $query, ?string $pageToken = null, int $max = 100): array
    {
        $this->guard();
        $this->queries[] = $query;

        preg_match('/from:\(([^)]*)\)/', $query, $f);
        $terms = array_map('trim', explode(' OR ', $f[1] ?? ''));
        $after = preg_match('/after:(\d+)/', $query, $a) ? (int) $a[1] : null;

        // Gmail returns newest first.
        $ids = collect($this->messages)
            ->filter(function (ParsedMessage $m) use ($terms, $after) {
                $from = (string) $m->from;
                $matches = collect($terms)->contains(fn ($t) => str_starts_with($t, '@') ? str_ends_with($from, $t) : $from === $t);

                return $matches && ($after === null || ($m->receivedAt?->getTimestamp() ?? 0) > $after);
            })
            ->sortByDesc(fn (ParsedMessage $m) => $m->receivedAt?->getTimestamp())
            ->keys()->values()->all();

        $offset = (int) ($pageToken ?? 0);
        $page = array_slice($ids, $offset, $max);
        $next = $offset + $max < count($ids) ? (string) ($offset + $max) : null;

        return ['ids' => $page, 'nextPageToken' => $next];
    }

    public function fromAddress(string $id): ?string
    {
        $this->guard();

        return $this->messages[$id]->from ?? null;
    }

    public function fetch(string $id): ParsedMessage
    {
        $this->guard();
        $this->fetches++;
        if ($this->failOnFetchNumber > 0 && $this->fetches === $this->failOnFetchNumber) {
            throw new MailboxUnavailable('The mailbox could not be read right now (simulated).');
        }

        return $this->messages[$id];
    }

    protected function guard(): void
    {
        if ($this->down) {
            throw new MailboxUnavailable('The mailbox could not be read right now (simulated).');
        }
    }
}
