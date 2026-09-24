<?php

namespace App\Modules\Mailer\Domain\Campaigns;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Domain\Brevo\BrevoUnavailable;
use App\Modules\Mailer\Domain\Brevo\BrevoNotConfigured;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Models\CampaignVehicle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Push a campaign to Brevo as a DRAFT (TOC-CMP-001, 003, 004, 007).
 *
 * Never sends or schedules: no scheduledAt is ever set, and BrevoGuard
 * blocks send endpoints anyway. Re-pushing updates the same Brevo draft;
 * if Brevo says it is no longer a draft, the push is refused.
 */
class CampaignPusher
{
    /** Interactive push must fail within 25 s when Brevo is down (TOC-NFR-005). */
    protected const BUDGET_SECONDS = 25;

    public function __construct(
        protected BrevoClient $client,
        protected CampaignRenderer $renderer,
        protected VehicleRecheck $recheck,
    ) {}

    /**
     * Re-check vehicles and store refreshed snapshots.
     *
     * @return list<array{vehicle_id: int, ref: string, type: string, message: string, blocking: bool}>
     */
    public function recheck(Campaign $campaign): array
    {
        $rows = $campaign->vehicles()->get();
        $result = $this->recheck->check($rows->map(fn (CampaignVehicle $cv) => $cv->snapshot)->all());

        DB::transaction(function () use ($rows, $result) {
            foreach ($rows->values() as $i => $row) {
                if ($row->snapshot != $result['snapshots'][$i]) {
                    $row->forceFill(['snapshot' => $result['snapshots'][$i]])->save();
                }
            }
        });

        return $result['issues'];
    }

    /** @return list<string> reasons the push is not possible yet (plain English) */
    public function problems(Campaign $campaign, array $issues = []): array
    {
        $count = $campaign->vehicles()->count();
        $min = (int) config('mailer.campaign.min_vehicles', 2);

        return array_values(array_filter([
            $count < $min ? "Add at least {$min} vehicles." : null,
            blank($campaign->subject) ? 'Add a subject line.' : null,
            blank($campaign->sender_id) ? 'Choose who the email is from.' : null,
            empty($campaign->list_ids) ? 'Choose at least one Brevo list.' : null,
            ...array_map(fn ($i) => $i['message'], array_filter($issues, fn ($i) => $i['blocking'])),
        ]));
    }

    /**
     * @throws PushRefused with a message safe to show staff
     */
    public function push(Campaign $campaign): Campaign
    {
        $issues = $this->recheck($campaign);
        if ($problems = $this->problems($campaign, $issues)) {
            throw new PushRefused(implode(' ', $problems), $issues);
        }

        $html = $this->renderer->render($campaign->fresh());
        $client = $this->client->withBudget(self::BUDGET_SECONDS);

        $payload = [
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'previewText' => (string) $campaign->preview_text,
            'sender' => ['id' => (int) $campaign->sender_id],
            'htmlContent' => $html,
            'recipients' => ['listIds' => array_values(array_map('intval', $campaign->list_ids))],
        ];

        try {
            if ($campaign->brevo_campaign_id) {
                $existing = $client->get("emailCampaigns/{$campaign->brevo_campaign_id}");

                if ($existing->status() === 404) {
                    $id = $this->create($client, $payload);
                } elseif ($existing->successful() && ($existing->json('status') ?? '') === 'draft') {
                    $client->put("emailCampaigns/{$campaign->brevo_campaign_id}", $payload)->throw();
                    $id = $campaign->brevo_campaign_id;
                } elseif ($existing->successful()) {
                    throw new PushRefused('This campaign is no longer a draft in Brevo (status: '.$existing->json('status').'). Duplicate this campaign to start a new one.', [], duplicate: true);
                } else {
                    throw new PushRefused('Brevo could not find this campaign (code '.$existing->status().').');
                }
            } else {
                $id = $this->create($client, $payload);
            }
        } catch (BrevoUnavailable|BrevoNotConfigured $e) {
            throw new PushRefused($e->getMessage());
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new PushRefused('Brevo did not accept the campaign: '.($e->response->json('message') ?? 'code '.$e->response->status()).'.');
        }

        $campaign->forceFill([
            'brevo_campaign_id' => $id,
            'pushed_at' => now(),
            'pushed_html_hash' => hash('sha256', $html),
            'status' => Campaign::STATUS_IN_BREVO,
        ])->save();

        return $campaign;
    }

    public function brevoUrl(Campaign $campaign): ?string
    {
        return $campaign->brevo_campaign_id
            ? sprintf((string) config('mailer.brevo.campaign_url'), $campaign->brevo_campaign_id)
            : null;
    }

    /** Changed since push when the HTML the push would send is different now (TOC-CB-006). */
    public function markChangedIfEdited(Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::STATUS_IN_BREVO || ! $campaign->pushed_html_hash) {
            return;
        }

        if (hash('sha256', $this->renderer->render($campaign->fresh())) !== $campaign->pushed_html_hash) {
            $campaign->forceFill(['status' => Campaign::STATUS_CHANGED])->save();
        }
    }

    protected function create(BrevoClient $client, array $payload): int
    {
        $id = $client->post('emailCampaigns', $payload)->throw()->json('id');
        if (! $id) {
            throw new RuntimeException('Brevo did not return a campaign id.');
        }

        return (int) $id;
    }
}
