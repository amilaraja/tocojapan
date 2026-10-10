<?php

namespace App\Modules\PartnerApi\Console;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Monitoring\AdminAlerts;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use App\Modules\PartnerApi\Models\PartnerWebhookDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Admin alerts (UI brief 6.1) and anomaly flags (SRS §8.3). Every 10 minutes:
 * holds ending soon, failing notifications, unusual traffic. Daily (--daily):
 * holds that ran out unused yesterday, contracts ending in 14 days.
 */
class MonitorPartners extends Command
{
    protected $signature = 'partners:monitor {--daily}';

    protected $description = 'Raise partner network alerts in the admin notification bell';

    /** Not-found answers on single vehicles in an hour that look like walking through stock numbers. */
    public const ENUMERATION_THRESHOLD = 100;

    /** A day this many times the 7-day average is flagged. */
    public const SPIKE_FACTOR = 5;

    public function handle(): int
    {
        $this->option('daily') ? $this->daily() : $this->frequent();

        return self::SUCCESS;
    }

    private function frequent(): void
    {
        VehicleHold::active()->whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addHours(6)])
            ->with(['vehicle', 'partner', 'referralPartner'])->each(fn (VehicleHold $hold) => AdminAlerts::holdExpiring($hold));

        Partner::query()->where('status', Partner::STATUS_ACTIVE)->whereNotNull('webhook_url')->each(function (Partner $partner) {
            $oldest = PartnerWebhookDelivery::where('partner_id', $partner->id)->where('status', PartnerWebhookDelivery::PENDING)
                ->where('attempts', '>', 0)->oldest()->first();
            if ($oldest && $oldest->created_at->lt(now()->subHour())) {
                AdminAlerts::webhooksFailing($partner, $oldest->last_error);
            }
        });

        Partner::query()->where('status', Partner::STATUS_ACTIVE)->each(function (Partner $partner) {
            $notFound = PartnerRequestLog::where('partner_id', $partner->id)->where('endpoint', 'partner.vehicles.show')
                ->where('status', 404)->where('created_at', '>=', now()->subHour())->count();
            if ($notFound >= self::ENUMERATION_THRESHOLD) {
                AdminAlerts::anomaly($partner, "{$notFound} requests for stock numbers that do not exist or are outside its scope in the last hour, which looks like walking through every stock number.");
            }

            $today = PartnerRequestLog::where('partner_id', $partner->id)->where('created_at', '>=', now()->subDay())->count();
            $week = PartnerRequestLog::where('partner_id', $partner->id)
                ->whereBetween('created_at', [now()->subDays(8), now()->subDay()])->count();
            $average = $week / 7;
            if ($average >= 200 && $today > self::SPIKE_FACTOR * $average) {
                AdminAlerts::anomaly($partner, number_format($today).' requests in the last 24 hours, more than '.self::SPIKE_FACTOR.' times its usual '.number_format((int) $average).' a day.');
            }
        });
    }

    private function daily(): void
    {
        $tz = config('partner_api.timezone');
        $yesterday = CarbonImmutable::now($tz)->subDay();

        AdminAlerts::holdsExpiredUnused(VehicleHold::with(['vehicle', 'partner', 'referralPartner'])
            ->where('state', VehicleHold::STATE_EXPIRED)
            ->whereBetween('ended_at', [$yesterday->startOfDay()->utc(), $yesterday->endOfDay()->utc()])->get());

        Partner::query()->where('status', '!=', Partner::STATUS_REVOKED)
            ->whereDate('contract_ends_on', CarbonImmutable::now($tz)->addDays(14)->toDateString())
            ->each(fn (Partner $partner) => AdminAlerts::contractEnding($partner));
    }
}
