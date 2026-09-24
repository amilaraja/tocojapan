<?php

namespace App\Modules\Mailer\Domain\Campaigns;

use App\Modules\Mailer\Models\Campaign;
use Illuminate\Support\Facades\DB;

/** TOC-CB-007: copy everything except the Brevo link, stats and status. */
class CampaignDuplicator
{
    public function duplicate(Campaign $source, ?int $userId = null): Campaign
    {
        return DB::transaction(function () use ($source, $userId) {
            $source = Campaign::query()->findOrFail($source->getKey()); // plain attributes only (no withCount extras)
            $copy = $source->replicate(['slug', 'status', 'brevo_campaign_id', 'pushed_at', 'pushed_html_hash', 'stats', 'stats_synced_at', 'sent_at', 'created_by']);
            $copy->name = mb_substr('Copy of '.$source->name, 0, 150);
            $copy->status = Campaign::STATUS_DRAFT;
            $copy->created_by = $userId;
            $copy->save();

            foreach ($source->vehicles()->get() as $v) {
                $copy->vehicles()->create([
                    'vehicle_id' => $v->vehicle_id, 'stock_ref' => $v->stock_ref, 'position' => $v->position,
                    'snapshot' => $v->snapshot, 'added_at' => now(),
                ]);
            }

            return $copy;
        });
    }
}
