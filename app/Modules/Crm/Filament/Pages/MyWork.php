<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmSlaTimer;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** "My work": own Cases by nearest deadline, tasks, follow-ups, stale, new (CRM-DSH-001). */
class MyWork extends Page
{
    protected string $view = 'crm::filament.my-work';

    protected static ?string $slug = 'my-work';

    protected static ?string $title = 'My work';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    /** @return Collection<int, CrmCase> */
    private function awayCases(): Collection
    {
        $away = \App\Modules\Crm\Models\CrmAgentStatus::whereNotNull('away_from')
            ->where(fn ($q) => $q->whereNull('away_until')->orWhere('away_until', '>=', now('Asia/Tokyo')->toDateString()))
            ->where('away_from', '<=', now('Asia/Tokyo')->addDays(3)->toDateString())
            ->pluck('user_id');

        return CrmCase::active()->whereIn('owner_id', $away)->with(['owner', 'customer', 'vehicle'])->orderBy('owner_id')->get();
    }

    /** @return array<string, Collection> */
    public function sections(): array
    {
        $me = auth()->id();
        $mine = CrmCase::active()->where('owner_id', $me)->with(['customer', 'vehicle']);

        $waiting = CrmSlaTimer::whereNull('met_at')->whereNull('cancelled_at')->whereIn('measure', ['first_response', 'customer_reply', 'quote_sent', 'payment', 'vessel_booked', 'documents_couriered'])
            ->whereHas('case', fn ($q) => $q->active()->where('owner_id', $me))
            ->with('case.customer', 'case.vehicle')->orderBy('due_at')->limit(50)->get();

        return [
            'deadlines' => $waiting,
            'followUps' => (clone $mine)->where('next_follow_up_at', '<=', now('Asia/Tokyo')->endOfDay()->utc())->orderBy('next_follow_up_at')->limit(50)->get(),
            'tasks' => CrmTask::whereNull('done_at')->where('assignee_id', $me)->where('due_at', '<=', now('Asia/Tokyo')->endOfDay()->utc())->with('case.customer')->orderBy('due_at')->get(),
            'stale' => (clone $mine)->whereNotNull('stale_at')->orderBy('last_staff_activity_at')->limit(50)->get(),
            'new' => (clone $mine)->where('assigned_at', '>=', now()->subDay())->latest('assigned_at')->limit(30)->get(),
            'unassigned' => CrmAccess::canPick() || CrmAccess::isAdmin()
                ? CrmCase::active()->whereNull('owner_id')->with(['customer', 'vehicle'])->oldest()->limit(30)->get()
                : collect(),
            // Staff away now or soon: their open Cases, for an admin to hand over (CRM-ASN-007).
            'away' => CrmAccess::isAdmin() ? $this->awayCases() : collect(),
            'operations' => CrmAccess::isOperations()
                ? CrmCase::active()->where('type', Crm::TYPE_VEHICLE)->whereIn('stage', [3, 4])->with(['customer', 'vehicle'])->orderBy('stage_entered_at')->limit(50)->get()
                : collect(),
        ];
    }
}
