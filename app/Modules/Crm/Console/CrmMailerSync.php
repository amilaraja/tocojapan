<?php

namespace App\Modules\Crm\Console;

use App\Modules\Crm\Domain\MailerHandshake;
use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Domain\Brevo\BrevoNotConfigured;
use App\Modules\Mailer\Domain\Brevo\BrevoUnavailable;
use Illuminate\Console\Command;

class CrmMailerSync extends Command
{
    protected $signature = 'crm:mailer-sync';

    protected $description = 'CRM: keep the Brevo "buying now" list that stock emails skip (hourly)';

    public function handle(BrevoClient $brevo): int
    {
        try {
            $result = MailerHandshake::sync($brevo->withBudget(60));
        } catch (BrevoUnavailable|BrevoNotConfigured $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }
        $this->line($result['note'] ?? "List {$result['list_id']}: +{$result['added']} -{$result['removed']}");

        return self::SUCCESS;
    }
}
