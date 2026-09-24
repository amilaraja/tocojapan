<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Domain\Campaigns\CampaignRenderer;
use App\Modules\Mailer\Domain\Vehicles\VehicleDTO;
use App\Modules\Mailer\Models\Campaign;
use Illuminate\Console\Command;
use Throwable;

/**
 * TOC-TPL-008: saves a static placeholder copy of the email in the Brevo
 * template library, inactive. Nothing is sent.
 */
class BrevoSaveTemplate extends Command
{
    protected $signature = 'mailer:brevo:save-template {--sender= : Brevo sender id (defaults to the first active sender)}';

    protected $description = 'Save a placeholder copy of the TOCO email in the Brevo template library (inactive)';

    public function handle(BrevoClient $client, CampaignRenderer $renderer): int
    {
        $placeholders = collect(range(1, 6))->map(fn (int $i) => VehicleDTO::fromArray([
            'id' => -$i, 'stockRef' => 'E0000'.$i, 'title' => 'VEHICLE TITLE '.$i, 'registrationYear' => 2020,
            'mileageKm' => 50000, 'transmission' => 'Automatic', 'priceFob' => 5000, 'previousPrice' => $i % 2 ? 5500 : null,
            'badge' => $i % 2 ? 'hot_deal' : 'new', 'status' => 'available', 'url' => 'https://tocojapan.com/vehicles',
        ]));

        $campaign = new Campaign([
            'slug' => 'template', 'subject' => 'TOCO International stock email',
            'preview_text' => 'Preview text', 'kicker' => 'KICKER', 'headline' => 'Headline goes here',
            'intro' => 'Intro paragraph goes here.',
        ]);

        try {
            $senderId = $this->option('sender') ?: collect($client->get('senders')->throw()->json('senders') ?? [])->firstWhere('active', true)['id'] ?? null;
            if (! $senderId) {
                $this->error('No active Brevo sender found.');

                return self::FAILURE;
            }

            $id = $client->post('smtp/templates', [
                'templateName' => 'TOCO Mailer – stock email (placeholder)',
                'subject' => 'TOCO International stock email',
                'sender' => ['id' => (int) $senderId],
                'htmlContent' => $renderer->renderWith($campaign, $placeholders),
                'isActive' => false,
            ])->throw()->json('id');

            $this->info("Saved in Brevo as template #{$id} (inactive).");
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
