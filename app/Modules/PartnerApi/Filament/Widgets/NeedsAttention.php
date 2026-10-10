<?php

namespace App\Modules\PartnerApi\Filament\Widgets;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Usage\Quota;
use App\Modules\PartnerApi\Filament\Pages\PartnerUsage;
use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerResource;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerWebhookDelivery;
use App\Modules\PartnerApi\Support\PartnerAccess;
use Filament\Widgets\Widget;

/**
 * Dashboard: one list of partner problems (UI brief 6.2): quotas nearly
 * used, failing notifications, contracts ending, and selling partners whose
 * holds keep running out unused (FR-HLD-07).
 */
class NeedsAttention extends Widget
{
    protected string $view = 'partner-api::widgets.needs-attention';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PartnerAccess::canAdmin();
    }

    /** @return list<array{partner: string, issue: string, url: string, level: string}> */
    public function items(): array
    {
        $items = [];

        foreach (Partner::query()->where('status', '!=', Partner::STATUS_REVOKED)->orderBy('name')->get() as $partner) {
            $usage = PartnerUsage::getUrl(['partner' => $partner->id]);

            foreach (['api' => 'requests', 'image' => 'photo downloads'] as $kind => $label) {
                $limit = Quota::dailyLimit($partner, $kind);
                $used = Quota::usedToday($partner, $kind);
                if ($limit > 0 && $used >= 0.8 * $limit) {
                    $items[] = ['partner' => $partner->name, 'issue' => 'Used '.(int) round($used / $limit * 100)."% of today's {$label}", 'url' => $usage, 'level' => 'warning'];
                }
            }

            $oldest = PartnerWebhookDelivery::where('partner_id', $partner->id)->where('status', PartnerWebhookDelivery::PENDING)
                ->where('attempts', '>', 0)->min('created_at');
            if ($oldest !== null && \Illuminate\Support\Carbon::parse($oldest)->lt(now()->subHour())) {
                $items[] = ['partner' => $partner->name, 'issue' => 'Change notifications failing for over an hour', 'url' => $usage, 'level' => 'danger'];
            }

            if ($partner->contract_ends_on && $partner->contract_ends_on->lte(now()->addDays(30))) {
                $items[] = ['partner' => $partner->name,
                    'issue' => $partner->contract_ends_on->isPast() ? 'Contract has ended' : 'Contract ends '.$partner->contract_ends_on->format('j M Y'),
                    'url' => PartnerResource::getUrl('edit', ['record' => $partner]), 'level' => $partner->contract_ends_on->isPast() ? 'danger' : 'warning'];
            }

            if ($partner->isSelling()) {
                $recent = VehicleHold::where('partner_id', $partner->id)->where('created_at', '>=', now()->subDays(30))
                    ->whereIn('state', [VehicleHold::STATE_EXPIRED, VehicleHold::STATE_CONVERTED, VehicleHold::STATE_RELEASED])->get();
                $expired = $recent->where('state', VehicleHold::STATE_EXPIRED)->count();
                if ($recent->count() >= 5 && $expired / $recent->count() >= 0.7) {
                    $items[] = ['partner' => $partner->name, 'issue' => "{$expired} of its last {$recent->count()} holds ran out without a sale",
                        'url' => $usage, 'level' => 'warning'];
                }
            }
        }

        return $items;
    }
}
