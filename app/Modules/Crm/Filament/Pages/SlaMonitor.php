<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmSlaTimer;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Breached and at-risk Cases, refreshed every minute (CRM-DSH-004). */
class SlaMonitor extends Page
{
    protected string $view = 'crm::filament.sla';

    protected static ?string $slug = 'sla';

    protected static ?string $title = 'SLA monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    /** @return array{breached: Collection<int, CrmSlaTimer>, risk: Collection<int, CrmSlaTimer>} */
    public function timers(): array
    {
        $visible = CrmAccess::scopeCases(CrmCase::query()->active())->select('id');
        $running = CrmSlaTimer::whereNull('met_at')->whereNull('cancelled_at')->whereNull('paused_at')->whereIn('case_id', $visible)
            ->with(['case.customer', 'case.owner', 'case.vehicle']);

        return [
            'breached' => (clone $running)->where(fn ($q) => $q->whereNotNull('breached_at')->orWhere('due_at', '<=', now()))->orderBy('due_at')->get(),
            'risk' => (clone $running)->whereNull('breached_at')->where('due_at', '>', now())->where('warn_at', '<=', now())->orderBy('due_at')->get(),
        ];
    }
}
