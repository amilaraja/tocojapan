<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmUi;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * CRM landing page (client change 2026-10-14): headline numbers, the pipeline by stage and trend charts.
 * Everything goes through CrmAccess::scopeCases, so agents see their own Cases and admins see all.
 */
class CrmDashboard extends Page
{
    protected string $view = 'crm::filament.dashboard';

    protected static ?string $slug = 'crm';

    protected static ?string $title = 'CRM Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 0;

    public const SOURCE_COLOURS = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b'];

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    private function cases(): Builder
    {
        return CrmAccess::scopeCases(CrmCase::query()->whereNull('merged_into_id'));
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        $tz = CrmUi::tz();
        $now = CarbonImmutable::now($tz);
        $monthStart = $now->startOfMonth()->utc();
        $active = fn () => $this->cases()->active();
        $late = fn (Builder $q) => $q->whereHas('slaTimers', fn ($t) => $t->whereNull('met_at')->whereNull('cancelled_at')->whereNotNull('breached_at'));

        // Pipeline: open vehicle sales per stage.
        $stageRows = $active()->where('type', Crm::TYPE_VEHICLE)
            ->selectRaw('stage, count(*) c, sum(coalesce(value_usd, 0)) v')->groupBy('stage')->get()->keyBy('stage');
        $stageLate = $late($active()->where('type', Crm::TYPE_VEHICLE))->selectRaw('stage, count(*) c')->groupBy('stage')->pluck('c', 'stage');
        $stages = collect(Crm::STAGES)->map(fn ($name, $n) => [
            'n' => $n, 'name' => $name, 'colour' => CrmUi::stageHex($n),
            'count' => (int) ($stageRows[$n]->c ?? 0), 'value' => (float) ($stageRows[$n]->v ?? 0), 'late' => (int) ($stageLate[$n] ?? 0),
        ])->values();

        // Last 12 weeks (Monday start, Tokyo time): new Cases and cars sold.
        $weekStart = $now->startOfWeek()->subWeeks(11);
        $created = $this->cases()->where('created_at', '>=', $weekStart->utc())->pluck('created_at');
        $won = $this->cases()->where('state', Crm::STATE_WON)->where('closed_at', '>=', $weekStart->utc())->pluck('closed_at');
        $bucket = fn ($dates) => $dates->countBy(fn ($d) => (int) floor($weekStart->diffInDays(CarbonImmutable::parse($d)->setTimezone($tz)) / 7));
        [$cBuckets, $wBuckets] = [$bucket($created), $bucket($won)];
        $weeks = collect(range(0, 11))->map(fn ($i) => [
            'label' => $weekStart->addWeeks($i)->format('j M'),
            'new' => (int) ($cBuckets[$i] ?? 0),
            'won' => (int) ($wBuckets[$i] ?? 0),
        ]);

        // Won value, last 6 months.
        $monthsFrom = $now->startOfMonth()->subMonths(5);
        $wonRows = $this->cases()->where('state', Crm::STATE_WON)->where('closed_at', '>=', $monthsFrom->utc())->get(['closed_at', 'value_usd']);
        $months = collect(range(0, 5))->map(function ($i) use ($monthsFrom, $wonRows, $tz) {
            $m = $monthsFrom->addMonths($i);
            $rows = $wonRows->filter(fn ($r) => $r->closed_at->setTimezone($tz)->format('Y-m') === $m->format('Y-m'));

            return ['label' => $m->format('M'), 'cars' => $rows->count(), 'value' => (float) $rows->sum('value_usd')];
        });

        // Sources, last 90 days.
        $since90 = $now->subDays(90)->utc();
        $sources = $this->cases()->where('created_at', '>=', $since90)
            ->selectRaw('source, count(*) c')->groupBy('source')->orderByDesc('c')->get()
            ->values()->map(fn ($r, $i) => ['label' => Crm::SOURCES[$r->source] ?? ucfirst((string) $r->source), 'count' => (int) $r->c, 'colour' => self::SOURCE_COLOURS[$i % count(self::SOURCE_COLOURS)]]);

        // Open Cases by type and by state.
        $types = $active()->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');
        $states = $active()->selectRaw('state, count(*) c')->groupBy('state')->pluck('c', 'state');

        $vehicle90 = $this->cases()->where('type', Crm::TYPE_VEHICLE)->where('created_at', '>=', $since90);
        $created90 = (clone $vehicle90)->count();
        $won90 = (clone $vehicle90)->where('state', Crm::STATE_WON)->count();
        $new7 = $this->cases()->where('created_at', '>=', $now->subDays(7)->utc())->count();
        $newPrev7 = $this->cases()->whereBetween('created_at', [$now->subDays(14)->utc(), $now->subDays(7)->utc()])->count();
        $wonMonth = $this->cases()->where('state', Crm::STATE_WON)->where('closed_at', '>=', $monthStart);

        // Late replies first, then Cases without an owner, then overdue follow-ups.
        $with = ['customer', 'owner', 'slaTimers' => fn ($q) => $q->whereNull('met_at')->whereNull('cancelled_at')];
        $attention = $late($active())->with($with)->orderBy('last_customer_message_at')->limit(8)->get();
        foreach ([fn (Builder $q) => $q->whereNull('owner_id'), fn (Builder $q) => $q->where('next_follow_up_at', '<', now())] as $rule) {
            if ($attention->count() < 8) {
                $attention = $attention->concat($rule($active())->whereNotIn('id', $attention->pluck('id'))->with($with)
                    ->orderBy('last_customer_message_at')->limit(8 - $attention->count())->get());
            }
        }

        return [
            'tiles' => [
                ['label' => 'Open Cases', 'value' => number_format($active()->count()), 'note' => number_format((int) ($types[Crm::TYPE_VEHICLE] ?? 0)).' car sales', 'from' => '#6366f1', 'to' => '#8b5cf6', 'url' => self::casesUrl()],
                ['label' => 'New this week', 'value' => number_format($new7), 'note' => self::trend($new7, $newPrev7), 'from' => '#0ea5e9', 'to' => '#22d3ee', 'url' => self::casesUrl()],
                ['label' => 'Late replies', 'value' => number_format($late($active())->count()), 'note' => 'past the reply deadline', 'from' => '#ef4444', 'to' => '#f97316', 'url' => SlaMonitor::getUrl()],
                ['label' => 'Unassigned', 'value' => number_format($active()->whereNull('owner_id')->count()), 'note' => 'waiting for an owner', 'from' => '#f59e0b', 'to' => '#facc15', 'url' => self::casesUrl(['owner_id' => ['value' => 'none']])],
                ['label' => 'Pipeline value', 'value' => '$'.number_format($stages->sum('value')), 'note' => 'open car sales', 'from' => '#14b8a6', 'to' => '#22c55e', 'url' => PipelineBoard::getUrl()],
                ['label' => 'Sold this month', 'value' => number_format((clone $wonMonth)->count()), 'note' => '$'.number_format((float) (clone $wonMonth)->sum('value_usd')), 'from' => '#10b981', 'to' => '#84cc16', 'url' => self::casesUrl(['state' => ['values' => [Crm::STATE_WON]]])],
                ['label' => 'Conversion', 'value' => ($created90 ? round($won90 * 100 / $created90) : 0).'%', 'note' => $won90.' of '.$created90.' car enquiries, 90 days', 'from' => '#ec4899', 'to' => '#a855f7', 'url' => CrmAccess::isAdmin() || CrmAccess::isTeamLead() ? Reports::getUrl() : null],
            ],
            'stages' => $stages,
            'weeks' => $weeks,
            'months' => $months,
            'sources' => $sources,
            'states' => collect(Crm::ACTIVE_STATES)->map(fn ($s, $i) => ['label' => Crm::STATES[$s], 'count' => (int) ($states[$s] ?? 0), 'colour' => ['#3b82f6', '#f59e0b', '#94a3b8'][$i]]),
            'attention' => $attention,
        ];
    }

    /** Cases list, optionally with table filters (same URL shape as saved views). */
    private static function casesUrl(array $filters = []): string
    {
        return CaseResource::getUrl('index').($filters ? '?'.http_build_query(['filters' => $filters]) : '');
    }

    private static function trend(int $now, int $before): string
    {
        if ($before === 0) {
            return $now ? 'up from 0 last week' : 'same as last week';
        }
        $pct = (int) round(($now - $before) * 100 / $before);

        return $pct === 0 ? 'same as last week' : ($pct > 0 ? '▲ ' : '▼ ').abs($pct).'% vs last week';
    }
}
