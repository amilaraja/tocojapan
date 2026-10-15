<?php

namespace App\Modules\Crm\Console;

use App\Models\User;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\Notifier;
use App\Modules\Crm\Mail\CrmMail;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmSlaTimer;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Crm\Support\CrmAccess;
use Illuminate\Console\Command;

/**
 * Morning summary (SRS §8.1 daily digest, proposal p.4): agents get their
 * overdue, due today and stale Cases; admins get the team summary.
 */
class CrmDigest extends Command
{
    protected $signature = 'crm:digest {--dry : Print instead of sending}';

    protected $description = 'CRM: daily summary emails to staff and admins';

    public function handle(): int
    {
        if (! Notifier::enabled('daily_digest', 'email')) {
            return self::SUCCESS;
        }
        $endOfDay = now('Asia/Tokyo')->endOfDay()->utc();
        $admins = CrmAccess::admins()->keyBy('id');

        foreach (CrmAccess::staff() as $user) {
            $lines = $this->agentLines($user, $endOfDay);
            if ($admins->has($user->id)) {
                $lines = array_merge($this->adminLines(), $lines ? ['', 'Your own Cases:'] : [], $lines);
            }
            if (! $lines || ! $user->email) {
                continue;
            }
            $text = "Good morning {$user->name},\n\n".implode("\n", $lines);
            if ($this->option('dry')) {
                $this->line("--- {$user->email}\n{$text}\n");

                continue;
            }
            Notifier::queue($user->email, new CrmMail(
                mailSubject: 'TOCO CRM: your day, '.now('Asia/Tokyo')->format('D j M'),
                text: $text,
                button: ['label' => 'Open My work', 'url' => url('/crm/my-work')],
            ), 'daily_digest', 'email');
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function agentLines(User $user, $endOfDay): array
    {
        $mine = CrmCase::active()->where('owner_id', $user->id);
        $overdue = (clone $mine)->where('next_follow_up_at', '<', now())->count();
        $today = (clone $mine)->whereBetween('next_follow_up_at', [now(), $endOfDay])->count();
        $stale = (clone $mine)->whereNotNull('stale_at')->count();
        $tasks = CrmTask::whereNull('done_at')->where('assignee_id', $user->id)->where('due_at', '<=', $endOfDay)->count();
        $breached = CrmSlaTimer::whereNull('met_at')->whereNull('cancelled_at')->whereNotNull('breached_at')->where('owner_id', $user->id)->count();

        if ($overdue + $today + $stale + $tasks + $breached === 0) {
            return [];
        }

        return array_values(array_filter([
            $breached ? "• {$breached} late replies (SLA breached)" : null,
            $overdue ? "• {$overdue} overdue follow-ups" : null,
            $today ? "• {$today} follow-ups due today" : null,
            $tasks ? "• {$tasks} tasks due today or overdue" : null,
            $stale ? "• {$stale} Cases with no activity for 3+ days" : null,
        ]));
    }

    /** @return list<string> */
    private function adminLines(): array
    {
        $since = now()->subDay();
        $new = CrmCase::where('created_at', '>=', $since)->count();
        $unassigned = CrmCase::active()->whereNull('owner_id')->count();
        $breached = CrmSlaTimer::whereNull('met_at')->whereNull('cancelled_at')->whereNotNull('breached_at')->whereHas('case', fn ($q) => $q->active())->count();
        $stale = CrmCase::active()->whereNotNull('stale_at')->count();
        $won = CrmCase::where('state', Crm::STATE_WON)->where('closed_at', '>=', now('Asia/Tokyo')->startOfMonth()->utc());
        $stages = CrmCase::active()->where('type', Crm::TYPE_VEHICLE)->selectRaw('stage, count(*) c')->groupBy('stage')->pluck('c', 'stage');

        $lines = [
            'Team summary:',
            "• {$new} new Cases in the last 24 hours",
            "• {$unassigned} not given to anyone yet",
            "• {$breached} late replies (SLA breached)",
            "• {$stale} Cases with no activity for 3+ days",
            '• Cars sold this month: '.$won->count().' (US$ '.number_format((float) $won->sum('value_usd')).')',
            '',
            'Open vehicle sales by step:',
        ];
        foreach (Crm::STAGES as $n => $name) {
            $lines[] = sprintf('• %02d %s: %d', $n, $name, (int) ($stages[$n] ?? 0));
        }

        return $lines;
    }
}
