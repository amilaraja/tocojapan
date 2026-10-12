<?php

namespace App\Modules\Crm\Domain;

use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmSlaTimer;
use App\Modules\Crm\Support\CrmSettings;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * SLA clocks per Case (CRM-SLA-001..003, 006). Reply measures run on business
 * time; stage measures (payment, vessel, documents) on calendar days.
 * Results are kept after the Case closes, for reporting.
 */
class SlaClock
{
    /** measure => [settings key, unit] */
    public const TARGETS = [
        'first_response' => ['sla.first_response_minutes', 'business'],
        'customer_reply' => ['sla.customer_reply_minutes', 'business'],
        'quote_sent' => ['sla.quote_sent_minutes', 'business'],
        'payment' => ['sla.payment_due_days', 'days'],
        'vessel_booked' => ['sla.vessel_booked_days', 'days'],
        'documents_couriered' => ['sla.documents_couriered_days', 'days'],
    ];

    public static function start(CrmCase $case, string $measure, ?DateTimeInterface $from = null): ?CrmSlaTimer
    {
        if (! isset(self::TARGETS[$measure]) || ! $case->isActive()) {
            return null;
        }

        // A clock already running keeps its earlier start (the customer has waited since then).
        $running = self::running($case, $measure);
        if ($running) {
            return $running;
        }
        // First response and stage measures are measured once per Case.
        if ($measure !== 'customer_reply' && $case->slaTimers()->where('measure', $measure)->whereNull('cancelled_at')->exists()) {
            return null;
        }

        [$key, $unit] = self::TARGETS[$measure];
        $target = (int) CrmSettings::get($key);
        $from = CarbonImmutable::instance($from ?? now());
        $minutes = $unit === 'days' ? $target * 1440 : $target;

        [$due, $warn] = self::schedule($from, $minutes, $unit);

        $timer = $case->slaTimers()->create([
            'measure' => $measure,
            'target_minutes' => $minutes,
            'started_at' => $from,
            'due_at' => $due,
            'warn_at' => $warn,
            'owner_id' => $case->owner_id,
        ]);

        // A Case already waiting on the customer starts paused (CRM-SLA-002).
        if (in_array($case->state, [Crm::STATE_WAITING, Crm::STATE_ON_HOLD], true)) {
            $timer->forceFill(['paused_at' => now()])->save();
        }

        return $timer;
    }

    public static function meet(CrmCase $case, string $measure, ?DateTimeInterface $at = null): void
    {
        $at ??= now();
        $timer = self::running($case, $measure);
        if ($timer) {
            $timer->forceFill(['met_at' => $at, 'paused_at' => null, 'owner_id' => $case->owner_id])->save();
        }
    }

    public static function cancel(CrmCase $case, ?string $measure = null): void
    {
        $case->slaTimers()->whereNull('met_at')->whereNull('cancelled_at')
            ->when($measure, fn ($q) => $q->where('measure', $measure))
            ->update(['cancelled_at' => now(), 'updated_at' => now()]);
    }

    /** Waiting on customer / On hold (CRM-SLA-002). */
    public static function pause(CrmCase $case): void
    {
        $case->slaTimers()->whereNull('met_at')->whereNull('cancelled_at')->whereNull('paused_at')
            ->update(['paused_at' => now(), 'updated_at' => now()]);
    }

    public static function resume(CrmCase $case): void
    {
        $calendar = new BusinessCalendar;
        $case->slaTimers()->whereNull('met_at')->whereNull('cancelled_at')->whereNotNull('paused_at')->get()
            ->each(function (CrmSlaTimer $timer) use ($calendar) {
                $unit = self::TARGETS[$timer->measure][1] ?? 'business';
                $paused = CarbonImmutable::instance($timer->paused_at);
                $now = CarbonImmutable::now();

                if ($unit === 'business') {
                    $due = $calendar->addMinutes($now, max(0, $calendar->minutesBetween($paused, $timer->due_at)));
                    $warn = $timer->warn_at && $timer->warned_at === null
                        ? $calendar->addMinutes($now, max(0, $calendar->minutesBetween($paused, $timer->warn_at)))
                        : $timer->warn_at;
                } else {
                    $seconds = $now->getTimestamp() - $paused->getTimestamp();
                    $due = CarbonImmutable::instance($timer->due_at)->addSeconds($seconds);
                    $warn = $timer->warn_at && $timer->warned_at === null ? CarbonImmutable::instance($timer->warn_at)->addSeconds($seconds) : $timer->warn_at;
                }

                $timer->forceFill(['due_at' => $due, 'warn_at' => $warn, 'paused_at' => null])->save();
            });
    }

    public static function running(CrmCase $case, string $measure): ?CrmSlaTimer
    {
        return $case->slaTimers()->where('measure', $measure)->whereNull('met_at')->whereNull('cancelled_at')->latest('id')->first();
    }

    /**
     * Green / amber / red for a Case (CRM-SLA-005): red when a clock is
     * breached, amber past the warning point, green otherwise; null if none runs.
     */
    public static function colour(CrmCase $case): ?string
    {
        $timers = $case->relationLoaded('slaTimers')
            ? $case->slaTimers->filter(fn (CrmSlaTimer $t) => $t->met_at === null && $t->cancelled_at === null)
            : $case->slaTimers()->whereNull('met_at')->whereNull('cancelled_at')->get();
        if ($timers->isEmpty()) {
            return null;
        }
        if ($timers->contains(fn (CrmSlaTimer $t) => $t->breached_at !== null || ($t->paused_at === null && $t->due_at->isPast()))) {
            return 'danger';
        }
        if ($timers->contains(fn (CrmSlaTimer $t) => $t->warned_at !== null || ($t->paused_at === null && $t->warn_at?->isPast()))) {
            return 'warning';
        }

        return 'success';
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} due and warning instants */
    private static function schedule(CarbonImmutable $from, int $minutes, string $unit): array
    {
        $percent = max(1, min(99, CrmSettings::int('sla.warning_percent')));
        $warnMinutes = (int) floor($minutes * $percent / 100);

        if ($unit === 'business') {
            $calendar = new BusinessCalendar;

            return [$calendar->addMinutes($from, $minutes), $calendar->addMinutes($from, $warnMinutes)];
        }

        return [$from->addMinutes($minutes), $from->addMinutes($warnMinutes)];
    }
}
