<?php

namespace App\Modules\Crm\Console;

use App\Modules\Crm\Domain\BusinessCalendar;
use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\Notifier;
use App\Modules\Crm\Domain\TemplateRenderer;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCaseStep;
use App\Modules\Crm\Models\CrmNotificationLog;
use App\Modules\Crm\Models\CrmSlaTimer;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Crm\Support\CrmSettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * Every minute: SLA warnings and breaches, stale Cases, unassigned
 * escalation, task reminders, payment reminders, holds ending, review
 * requests (CRM-SLA-003, 004, ASN-006, ACT-004, SRS §8.1).
 */
class CrmTick extends Command
{
    protected $signature = 'crm:tick';

    protected $description = 'CRM: SLA warnings and breaches, stale Cases, reminders (runs every minute)';

    public function handle(): int
    {
        foreach (['sla', 'stale', 'unassigned', 'tasks', 'paymentDue', 'holds', 'reviews'] as $step) {
            try {
                $this->{$step}();
            } catch (Throwable $e) {
                report($e);
                $this->error("{$step}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function sla(): void
    {
        $running = CrmSlaTimer::query()->whereNull('met_at')->whereNull('cancelled_at')->whereNull('paused_at')->with('case');

        (clone $running)->whereNull('warned_at')->whereNull('breached_at')->where('warn_at', '<=', now())->where('due_at', '>', now())
            ->each(function (CrmSlaTimer $timer) {
                $timer->forceFill(['warned_at' => now()])->save();
                if ($timer->case?->isActive()) {
                    Notifier::event('sla_warning', $timer->case, $this->slaContext($timer));
                }
            });

        (clone $running)->whereNull('breached_at')->where('due_at', '<=', now())
            ->each(function (CrmSlaTimer $timer) {
                $timer->forceFill(['breached_at' => now(), 'warned_at' => $timer->warned_at ?? now()])->save();
                if ($timer->case?->isActive()) {
                    Notifier::event('sla_breached', $timer->case, $this->slaContext($timer));
                }
            });
    }

    /** @return array<string, string> */
    private function slaContext(CrmSlaTimer $timer): array
    {
        return [
            'measure_label' => CrmSlaTimer::MEASURES[$timer->measure] ?? $timer->measure,
            'due' => $timer->due_at->copy()->timezone('Asia/Tokyo')->format('D j M H:i').' JST',
        ];
    }

    private function stale(): void
    {
        $days = CrmSettings::int('sla.stale_days');
        $adminDays = CrmSettings::int('sla.stale_admin_days');

        $base = CrmCase::active()->where('state', '!=', Crm::STATE_ON_HOLD);

        (clone $base)->whereNull('stale_at')
            ->whereRaw('COALESCE(last_staff_activity_at, created_at) <= ?', [now()->subDays($days)])
            ->each(function (CrmCase $case) use ($days) {
                $case->forceFill(['stale_at' => now()])->saveQuietly();
                Notifier::event('case_stale', $case, ['days' => $days]);
            });

        (clone $base)->whereNotNull('stale_at')->whereNull('stale_admin_at')
            ->whereRaw('COALESCE(last_staff_activity_at, created_at) <= ?', [now()->subDays($adminDays)])
            ->each(function (CrmCase $case) use ($adminDays) {
                $case->forceFill(['stale_admin_at' => now()])->saveQuietly();
                Notifier::event('case_stale', $case, ['days' => $adminDays, 'admin' => true]);
            });
    }

    /** Unassigned longer than the escalation time, in business hours (CRM-ASN-006). */
    private function unassigned(): void
    {
        $limit = CrmSettings::int('sla.unassigned_escalation_minutes');
        $calendar = new BusinessCalendar;

        CrmCase::active()->whereNull('owner_id')->whereNull('unassigned_alerted_at')->each(function (CrmCase $case) use ($limit, $calendar) {
            if ($calendar->minutesBetween($case->created_at, now()) >= $limit) {
                $case->forceFill(['unassigned_alerted_at' => now()])->saveQuietly();
                Notifier::event('case_unassigned', $case, ['since' => $case->created_at->copy()->timezone('Asia/Tokyo')->format('D j M H:i').' JST']);
            }
        });
    }

    private function tasks(): void
    {
        CrmTask::whereNull('done_at')->whereNull('reminded_at')->where('due_at', '<=', now())->with('case')
            ->each(function (CrmTask $task) {
                $task->forceFill(['reminded_at' => now()])->save();
                if ($task->case) {
                    Notifier::event('task_due', $task->case, ['task' => $task->title, 'assignee_id' => $task->assignee_id]);
                }
            });
    }

    /** Customer reminder before the payment due date (SRS §7.1, §8.1). */
    private function paymentDue(): void
    {
        $before = CrmSettings::int('email.payment_reminder_days_before');

        CrmSlaTimer::where('measure', 'payment')->whereNull('met_at')->whereNull('cancelled_at')->whereNull('paused_at')
            ->where('due_at', '<=', now()->addDays($before))->where('due_at', '>', now())->with('case')
            ->each(function (CrmSlaTimer $timer) {
                $case = $timer->case;
                if (! $case?->isActive() || CrmNotificationLog::where('case_id', $case->id)->where('event', 'payment_due')->where('created_at', '>=', $timer->started_at)->exists()) {
                    return;
                }
                CrmNotificationLog::create(['case_id' => $case->id, 'event' => 'payment_due', 'channel' => 'system', 'recipient' => '-', 'status' => 'scheduled']);
                Notifier::event('payment_due', $case);
            });
    }

    /** On hold until a date: back to Open on that date. */
    private function holds(): void
    {
        CrmCase::where('state', Crm::STATE_ON_HOLD)->whereNotNull('hold_until')->where('hold_until', '<=', now())->whereNull('merged_into_id')
            ->each(fn (CrmCase $case) => CaseActions::setState($case, Crm::STATE_OPEN, null, 'Hold ended', ['system' => true]));
    }

    /** Thank-you and review request a few days after "Car received" (SRS §3.2, OPEN-12). */
    private function reviews(): void
    {
        $delay = CrmSettings::int('email.review_delay_days');

        CrmCase::where('state', Crm::STATE_WON)->where('type', Crm::TYPE_VEHICLE)->where('closed_at', '<=', now()->subDays($delay))
            ->where('closed_at', '>=', now()->subDays($delay + 30))
            ->whereHas('steps', fn ($q) => $q->whereNull('done_at')->whereHas('template', fn ($t) => $t->where('key', 'review_requested')))
            ->each(function (CrmCase $case) {
                $step = $case->steps()->whereHas('template', fn ($t) => $t->where('key', 'review_requested'))->first();
                $step?->forceFill(['done_at' => now(), 'done_automatically' => true])->save();
                Notifier::event('case_won', $case, ['only_customer' => true]);
            });
    }
}
