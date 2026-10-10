<?php

namespace App\Modules\PartnerApi\Console;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Webhooks\WebhookOutbox;
use App\Modules\PartnerApi\Domain\Webhooks\WebhookSender;
use Illuminate\Console\Command;

/**
 * Every minute: warn selling partners about holds ending within 6 hours
 * (FR-HLD-04), then deliver due change notifications for up to 40 seconds
 * (the scheduler's worker budget), so notices go out within 2 minutes.
 */
class SendWebhooks extends Command
{
    protected $signature = 'partners:webhooks {--seconds=40}';

    protected $description = 'Send partner change notifications that are due';

    public function handle(WebhookSender $sender): int
    {
        VehicleHold::active()->where('channel', VehicleHold::CHANNEL_PARTNER)
            ->whereNotNull('expires_at')->where('expires_at', '<=', now()->addHours(6))->whereNull('expiry_warned_at')
            ->with(['partner', 'vehicle'])->each(function (VehicleHold $hold) {
                WebhookOutbox::holdEvent($hold, WebhookOutbox::HOLD_EXPIRING);
                $hold->forceFill(['expiry_warned_at' => now()])->saveQuietly();
            });

        $count = $sender->sendDue((int) $this->option('seconds'));
        if ($count > 0) {
            $this->info("Attempted {$count} notification(s).");
        }

        return self::SUCCESS;
    }
}
