<?php

namespace App\Modules\Crm\Support;

use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\SlaClock;
use App\Modules\Crm\Models\CrmAgentStatus;
use App\Modules\Crm\Models\CrmCase;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Small display helpers shared by the CRM admin screens. */
class CrmUi
{
    /** The viewer's own time zone (CRM-NFR-005). */
    public static function tz(): string
    {
        static $tz = [];
        $id = auth()->id();

        return $tz[$id] ??= CrmAgentStatus::timezoneFor($id);
    }

    public static function time(?DateTimeInterface $at, string $format = 'D j M H:i'): string
    {
        return $at ? Carbon::instance($at)->timezone(self::tz())->format($format) : '—';
    }

    public static function ago(?DateTimeInterface $at): string
    {
        return $at ? Carbon::instance($at)->diffForHumans(short: true) : '—';
    }

    public static function stateColor(?string $state): string
    {
        return match ($state) {
            Crm::STATE_OPEN => 'info',
            Crm::STATE_WAITING => 'gray',
            Crm::STATE_ON_HOLD => 'warning',
            Crm::STATE_WON => 'success',
            Crm::STATE_LOST => 'danger',
            default => 'gray',
        };
    }

    /** Green / amber / red against the SLA (CRM-SLA-005). */
    public static function slaColor(CrmCase $case): string
    {
        return SlaClock::colour($case) ?? 'gray';
    }

    public static function slaHex(?string $colour): string
    {
        return match ($colour) {
            'danger' => '#dc2626',
            'warning' => '#d97706',
            'success' => '#16a34a',
            default => '#9ca3af',
        };
    }

    /** "3 d 4 h" for time in stage (CRM-STG-008). */
    public static function duration(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $days ? "{$days} days {$hours} hours" : ($hours ? "{$hours} hours {$minutes} min" : "{$minutes} min");
    }

    /** @return array<int, string> */
    public static function staffOptions(): array
    {
        return CrmAccess::staff()->mapWithKeys(fn ($u) => [$u->id => $u->name.(($n = CrmAgentStatus::nicknameFor($u->id)) ? " ({$n})" : '')])->all();
    }
}
