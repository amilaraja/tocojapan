<?php

namespace App\Modules\Mailer\Domain\Importer;

/** Read-only access to the Mailbox. GmailReader in production, fakes in tests. */
interface MailboxReader
{
    public function isConfigured(): bool;

    public function currentHistoryId(): ?string;

    /**
     * Message ids added since a history id.
     *
     * @return list<string>
     *
     * @throws HistoryExpired when the history id is too old
     */
    public function idsSinceHistory(string $historyId): array;

    /** @return array{ids: list<string>, nextPageToken: ?string} */
    public function search(string $query, ?string $pageToken = null, int $max = 100): array;

    /** Sender address from headers only (no body is downloaded). */
    public function fromAddress(string $id): ?string;

    public function fetch(string $id): ParsedMessage;
}
