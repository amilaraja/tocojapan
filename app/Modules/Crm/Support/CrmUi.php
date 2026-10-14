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
    /** One colour per stage, used on every CRM screen (client change 2026-10-14: uniformly colourful). */
    public const STAGE_COLOURS = [1 => '#6366f1', 2 => '#f59e0b', 3 => '#0ea5e9', 4 => '#a855f7', 5 => '#10b981'];

    /** Accent colours for sections, avatars and charts, in order. */
    public const PALETTE = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#a855f7', '#ec4899', '#14b8a6', '#f97316', '#84cc16'];

    public static function stageHex(?int $stage): string
    {
        return self::STAGE_COLOURS[$stage] ?? '#64748b';
    }

    /** Case state as a hex colour (pills). */
    public static function stateHex(?string $state): string
    {
        return match ($state) {
            Crm::STATE_OPEN => '#3b82f6',
            Crm::STATE_WAITING => '#f59e0b',
            Crm::STATE_ON_HOLD => '#94a3b8',
            Crm::STATE_WON => '#10b981',
            Crm::STATE_LOST => '#ef4444',
            default => '#64748b',
        };
    }

    /** A stable colour per staff member, for avatars. */
    public static function personHex(?int $userId): string
    {
        return $userId ? self::PALETTE[$userId % count(self::PALETTE)] : '#94a3b8';
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return $name ? mb_strtoupper(mb_substr($parts[0], 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '')) : '?';
    }

    /** Green under 3 days in a stage, amber under 7, red after. */
    public static function ageHex(int $days): string
    {
        return $days < 3 ? '#10b981' : ($days < 7 ? '#f59e0b' : '#ef4444');
    }

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
