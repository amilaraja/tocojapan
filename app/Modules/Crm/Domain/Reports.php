<?php

namespace App\Modules\Crm\Domain;

use App\Models\User;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmSlaTimer;
use App\Modules\Crm\Models\CrmStageHistory;
use App\Modules\Crm\Support\CrmAccess;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the team overview and the reports (CRM-DSH-003, 005), from
 * stored SLA timers and stage history, so closed Cases still count (SLA-006).
 */
class Reports
{
    /** Per staff member (CRM-DSH-003). @return Collection<int, array<string, mixed>> */
    public static function team(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $monthStart = now('Asia/Tokyo')->startOfMonth()->utc();

        return CrmAccess::staff()->map(function (User $user) use ($from, $to, $monthStart) {
            $open = CrmCase::active()->where('owner_id', $user->id);
            $timers = CrmSlaTimer::where('owner_id', $user->id)->whereBetween('started_at', [$from, $to])->whereNull('cancelled_at');
            $closed = (clone $timers)->where(fn ($q) => $q->whereNotNull('met_at')->orWhereNotNull('breached_at'));
            $total = (clone $closed)->count();
            $onTime = (clone $closed)->whereNull('breached_at')->count();
            $first = (clone $timers)->where('measure', 'first_response')->whereNotNull('met_at')->get();

            return [
                'user' => $user,
                'open' => (clone $open)->count(),
                'by_stage' => (clone $open)->where('type', Crm::TYPE_VEHICLE)->selectRaw('stage, count(*) c')->groupBy('stage')->pluck('c', 'stage')->all(),
                'avg_first_response' => $first->isEmpty() ? null : (int) round($first->avg(fn ($t) => $t->met_at->getTimestamp() - $t->started_at->getTimestamp()) / 60),
                'sla_percent' => $total ? (int) round($onTime * 100 / $total) : null,
                'overdue_follow_ups' => (clone $open)->where('next_follow_up_at', '<', now())->count(),
                'stale' => (clone $open)->whereNotNull('stale_at')->count(),
                'last_activity' => DB::table('crm_activities')->where('user_id', $user->id)->max('created_at'),
                'won_month' => CrmCase::where('owner_id', $user->id)->where('state', Crm::STATE_WON)->where('closed_at', '>=', $monthStart)->count(),
            ];
        });
    }

    /** How many Cases created in the period reached each stage (CRM-DSH-005). @return array<int, int> */
    public static function funnel(CarbonInterface $from, CarbonInterface $to): array
    {
        $ids = CrmCase::where('type', Crm::TYPE_VEHICLE)->whereBetween('created_at', [$from, $to])->whereNull('merged_into_id')->pluck('id');
        $reached = CrmStageHistory::whereIn('case_id', $ids)->selectRaw('stage, count(distinct case_id) c')->groupBy('stage')->pluck('c', 'stage');
        $won = CrmCase::whereIn('id', $ids)->where('state', Crm::STATE_WON)->count();

        $out = [];
        foreach (array_keys(Crm::STAGES) as $stage) {
            $out[$stage] = (int) ($reached[$stage] ?? 0);
        }
        $out[6] = $won;

        return $out;
    }

    /** @return Collection<int, object> */
    public static function lostReasons(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CrmCase::where('state', Crm::STATE_LOST)->whereBetween('closed_at', [$from, $to])
            ->selectRaw('reason, count(*) c, sum(value_usd) v')->groupBy('reason')->orderByDesc('c')->get();
    }

    /** Cases, won and conversion per source. @return Collection<int, array<string, mixed>> */
    public static function sources(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CrmCase::whereBetween('created_at', [$from, $to])->whereNull('merged_into_id')
            ->selectRaw("source, count(*) c, sum(case when state = 'won' then 1 else 0 end) w, sum(case when state = 'won' then value_usd else 0 end) v")
            ->groupBy('source')->orderByDesc('c')->get()
            ->map(fn ($r) => ['source' => Crm::SOURCES[$r->source] ?? $r->source, 'cases' => (int) $r->c, 'won' => (int) $r->w, 'value' => (float) $r->v, 'conversion' => $r->c ? round($r->w * 100 / $r->c, 1) : 0]);
    }

    /** Average days per stage, from finished stage stays. @return array<int, ?float> */
    public static function timeInStage(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = CrmStageHistory::whereNotNull('left_at')->whereBetween('left_at', [$from, $to])->get();
        $out = [];
        foreach (array_keys(Crm::STAGES) as $stage) {
            $stays = $rows->where('stage', $stage);
            $out[$stage] = $stays->isEmpty() ? null : round($stays->avg(fn ($r) => $r->left_at->getTimestamp() - $r->entered_at->getTimestamp()) / 86400, 1);
        }

        return $out;
    }

    /** Average and on-time share per SLA measure. @return Collection<int, array<string, mixed>> */
    public static function responseTimes(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return collect(CrmSlaTimer::MEASURES)->map(function ($label, $measure) use ($from, $to) {
            $timers = CrmSlaTimer::where('measure', $measure)->whereBetween('started_at', [$from, $to])->whereNull('cancelled_at')->get();
            $met = $timers->whereNotNull('met_at');
            $finished = $timers->filter(fn ($t) => $t->met_at || $t->breached_at);

            return [
                'measure' => $label,
                'count' => $timers->count(),
                'avg_minutes' => $met->isEmpty() ? null : (int) round($met->avg(fn ($t) => $t->met_at->getTimestamp() - $t->started_at->getTimestamp()) / 60),
                'on_time' => $finished->isEmpty() ? null : (int) round($finished->whereNull('breached_at')->count() * 100 / $finished->count()),
                'breached' => $timers->whereNotNull('breached_at')->count(),
            ];
        })->values();
    }

    /** @return Collection<int, object> */
    public static function wonByMonth(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CrmCase::where('state', Crm::STATE_WON)->whereBetween('closed_at', [$from, $to])->get(['closed_at', 'value_usd'])
            ->groupBy(fn ($c) => $c->closed_at->timezone('Asia/Tokyo')->format('Y-m'))
            ->map(fn ($g, $month) => (object) ['month' => $month, 'c' => $g->count(), 'v' => (float) $g->sum('value_usd')])
            ->sortKeys()->values();
    }

    /** @return Collection<int, object> */
    public static function wonByCountry(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CrmCase::where('crm_cases.state', Crm::STATE_WON)->whereBetween('crm_cases.closed_at', [$from, $to])
            ->leftJoin('countries', 'countries.id', '=', 'crm_cases.dest_country_id')
            ->selectRaw("coalesce(countries.name, 'Unknown') country, count(*) c, sum(crm_cases.value_usd) v")
            ->groupBy('country')->orderByDesc('v')->get();
    }
}
