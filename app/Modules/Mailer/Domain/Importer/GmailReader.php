<?php

namespace App\Modules\Mailer\Domain\Importer;

use App\Modules\Mailer\Support\MailerSettings;
use Google\Client;
use Google\Service\Exception as GoogleException;
use Google\Service\Gmail;
use GuzzleHttp\Client as Guzzle;
use Throwable;

/**
 * Gmail access with a service account + domain-wide delegation.
 * Scope is gmail.readonly ONLY (hard rule 2, TOC-IMP-001). 20 s timeout.
 */
class GmailReader implements MailboxReader
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    protected ?Gmail $service = null;

    public function __construct(protected MailerSettings $settings) {}

    public function isConfigured(): bool
    {
        $path = $this->keyPath();

        return filled($this->mailbox()) && $path !== null && is_readable($path);
    }

    public function mailbox(): ?string
    {
        return $this->settings->get('mailbox') ?: null;
    }

    /** The configured Google client; exposed so tests can assert the scope. */
    public function client(): Client
    {
        if (! $this->isConfigured()) {
            throw new MailboxUnavailable('The mailbox is not connected yet. Add the mailbox address and Google key file in Mailer settings.');
        }

        $client = new Client;
        $client->setAuthConfig((string) $this->keyPath());
        $client->setScopes([self::SCOPE]);
        $client->setSubject((string) $this->mailbox());
        $client->setHttpClient(new Guzzle([
            'timeout' => (int) config('mailer.google.timeout', 20),
            'connect_timeout' => 10,
        ]));

        return $client;
    }

    public function currentHistoryId(): ?string
    {
        return $this->call(fn (Gmail $g) => (string) $g->users->getProfile('me')->getHistoryId());
    }

    public function idsSinceHistory(string $historyId): array
    {
        $ids = [];
        $pageToken = null;

        do {
            try {
                $page = $this->service()->users_history->listUsersHistory('me', array_filter([
                    'startHistoryId' => $historyId,
                    'historyTypes' => 'messageAdded',
                    'pageToken' => $pageToken,
                    'maxResults' => 500,
                ]));
            } catch (GoogleException $e) {
                if ($e->getCode() === 404) {
                    throw new HistoryExpired('Gmail history is too old.');
                }
                throw $this->unavailable($e);
            } catch (Throwable $e) {
                throw $this->unavailable($e);
            }

            foreach ($page->getHistory() ?? [] as $h) {
                foreach ($h->getMessagesAdded() ?? [] as $added) {
                    $ids[] = (string) $added->getMessage()->getId();
                }
            }
            $pageToken = $page->getNextPageToken();
        } while ($pageToken);

        return array_values(array_unique($ids));
    }

    public function search(string $query, ?string $pageToken = null, int $max = 100): array
    {
        return $this->call(function (Gmail $g) use ($query, $pageToken, $max) {
            $page = $g->users_messages->listUsersMessages('me', array_filter([
                'q' => $query,
                'maxResults' => $max,
                'pageToken' => $pageToken,
            ]));

            return [
                'ids' => array_map(fn ($m) => (string) $m->getId(), $page->getMessages() ?? []),
                'nextPageToken' => $page->getNextPageToken() ?: null,
            ];
        });
    }

    public function fromAddress(string $id): ?string
    {
        return $this->call(function (Gmail $g) use ($id) {
            $msg = $g->users_messages->get('me', $id, ['format' => 'metadata', 'metadataHeaders' => ['From']]);
            foreach ($msg->getPayload()?->getHeaders() ?? [] as $h) {
                if (strtolower((string) $h->getName()) === 'from') {
                    return MessageParser::address((string) $h->getValue());
                }
            }

            return null;
        });
    }

    public function fetch(string $id): ParsedMessage
    {
        return $this->call(fn (Gmail $g) => MessageParser::fromGmail($g->users_messages->get('me', $id, ['format' => 'full'])));
    }

    protected function service(): Gmail
    {
        return $this->service ??= new Gmail($this->client());
    }

    /**
     * @template T
     *
     * @param  callable(Gmail): T  $fn
     * @return T
     */
    protected function call(callable $fn): mixed
    {
        try {
            return $fn($this->service());
        } catch (MailboxUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->unavailable($e);
        }
    }

    protected function unavailable(Throwable $e): MailboxUnavailable
    {
        $code = $e->getCode();

        return new MailboxUnavailable(match (true) {
            $code === 401 || $code === 403 => 'Google refused access to the mailbox. Check the key file and that read-only access is approved for this mailbox.',
            default => 'The mailbox could not be read right now ('.class_basename($e).'). The next run will try again.',
        }, 0, $e);
    }

    protected function keyPath(): ?string
    {
        return $this->settings->get('google_key_path') ?: null;
    }
}
