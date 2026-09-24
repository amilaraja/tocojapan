<?php

namespace App\Modules\Mailer\Domain\Brevo;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Brevo lists and senders for pickers (TOC-BRV-007). Loaded from Brevo,
 * cached for an hour; "Refresh" calls forget().
 */
class BrevoDirectory
{
    protected const TTL = 3600;

    public function __construct(protected BrevoClient $client) {}

    /** @return array<int, string> list id => "Name (123 contacts)" */
    public function lists(): array
    {
        return $this->remember('mailer:brevo:lists', function (): array {
            $out = [];
            $offset = 0;
            do {
                $page = $this->client->withBudget(20)->get('contacts/lists', ['limit' => 50, 'offset' => $offset])->throw()->json();
                foreach ($page['lists'] ?? [] as $list) {
                    $out[(int) $list['id']] = $list['name'].(isset($list['totalSubscribers']) ? ' ('.number_format((int) $list['totalSubscribers']).')' : '');
                }
                $offset += 50;
            } while (count($page['lists'] ?? []) === 50);

            return $out;
        });
    }

    /** @return array<int, string> sender id => "Name <email>" (active senders only) */
    public function senders(): array
    {
        return $this->remember('mailer:brevo:senders', function (): array {
            $out = [];
            foreach ($this->client->withBudget(20)->get('senders')->throw()->json('senders') ?? [] as $s) {
                if (($s['active'] ?? true) === false) {
                    continue;
                }
                $out[(int) $s['id']] = $s['name'].' <'.$s['email'].'>';
            }

            return $out;
        });
    }

    /** @return array{id: int, name: string, email: string}|null */
    public function sender(int $id): ?array
    {
        $label = $this->senders()[$id] ?? null;
        if (! $label || ! preg_match('/^(.*) <(.+)>$/', $label, $m)) {
            return null;
        }

        return ['id' => $id, 'name' => $m[1], 'email' => $m[2]];
    }

    public function forget(): void
    {
        Cache::forget('mailer:brevo:lists');
        Cache::forget('mailer:brevo:senders');
    }

    /** Empty array (not an exception) when Brevo is not connected or unreachable. */
    protected function remember(string $key, callable $load): array
    {
        if (! $this->client->isConfigured()) {
            return [];
        }

        try {
            return Cache::remember($key, self::TTL, $load);
        } catch (Throwable) {
            return [];
        }
    }
}
