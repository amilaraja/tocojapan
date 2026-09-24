<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use Illuminate\Console\Command;
use Throwable;

/** Read-only: key works, which senders and lists exist. */
class BrevoCheck extends Command
{
    protected $signature = 'mailer:brevo:check';

    protected $description = 'Read-only check of the Brevo key, senders and lists';

    public function handle(BrevoClient $client): int
    {
        try {
            $account = $client->get('account')->throw()->json();
            $this->info('Brevo account: '.($account['companyName'] ?? '?').' <'.($account['email'] ?? '?').'>');
            foreach ($account['plan'] ?? [] as $plan) {
                $this->line('  Plan: '.($plan['type'] ?? '?').' credits '.($plan['credits'] ?? '?'));
            }

            $this->info('Senders:');
            foreach ($client->get('senders')->throw()->json('senders') ?? [] as $s) {
                $this->line(sprintf('  #%d %s <%s> %s', $s['id'], $s['name'], $s['email'], ($s['active'] ?? false) ? 'active' : 'NOT active'));
            }

            $this->info('Lists:');
            foreach ($client->get('contacts/lists', ['limit' => 50])->throw()->json('lists') ?? [] as $l) {
                $this->line(sprintf('  #%d %s (%d contacts)', $l['id'], $l['name'], $l['totalSubscribers'] ?? 0));
            }

            $attrs = collect($client->get('contacts/attributes')->throw()->json('attributes') ?? [])->pluck('name')->all();
            $missing = array_diff(BrevoSetup::ATTRIBUTES_NEEDED, $attrs);
            $missing
                ? $this->warn('Missing contact attributes: '.implode(', ', $missing).' (run mailer:brevo:setup)')
                : $this->info('All contact attributes present.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
