<?php

namespace App\Modules\Mailer\Domain\Campaigns;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Models\Campaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/** TOC-CMP-006: hourly status and summary statistics for campaigns pushed in the last 60 days. */
class StatsSync
{
    public function __construct(protected BrevoClient $client) {}

    public function run(): int
    {
        if (! $this->client->isConfigured()) {
            return 0;
        }

        $synced = 0;
        $campaigns = Campaign::query()
            ->whereNotNull('brevo_campaign_id')
            ->where('pushed_at', '>=', now()->subDays(60))
            ->where('status', '!=', Campaign::STATUS_ARCHIVED)
            ->get();

        foreach ($campaigns as $campaign) {
            try {
                $this->sync($campaign);
                $synced++;
            } catch (Throwable $e) {
                Log::warning('Mailer: stats sync failed', ['campaign' => $campaign->id, 'error' => $e->getMessage()]);
            }
        }

        return $synced;
    }

    public function sync(Campaign $campaign): void
    {
        $data = $this->client->get("emailCampaigns/{$campaign->brevo_campaign_id}", ['statistics' => 'globalStats'])->throw()->json();
        $global = $data['statistics']['globalStats'] ?? [];

        $update = [
            'stats' => [
                'recipients' => (int) ($global['sent'] ?? $global['delivered'] ?? 0),
                'delivered' => (int) ($global['delivered'] ?? 0),
                'opens' => (int) ($global['uniqueViews'] ?? 0),
                'clicks' => (int) ($global['uniqueClicks'] ?? 0),
                'unsubscribes' => (int) ($global['unsubscriptions'] ?? 0),
            ],
            'stats_synced_at' => now(),
        ];

        if (($data['status'] ?? null) === 'sent') {
            $update['status'] = Campaign::STATUS_SENT;
            $update['sent_at'] = isset($data['sentDate']) ? Carbon::parse($data['sentDate']) : ($campaign->sent_at ?? now());
        }

        $campaign->forceFill($update)->save();
    }
}
