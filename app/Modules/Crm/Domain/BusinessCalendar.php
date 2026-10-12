<?php

namespace App\Modules\Crm\Domain;

use App\Modules\Crm\Models\CrmHoliday;
use App\Modules\Crm\Support\CrmSettings;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Business-time arithmetic on the configured hours and holidays, Asia/Tokyo
 * (SRS §1.4, CRM-SLA-001). Returns UTC instants.
 */
class BusinessCalendar
{
    /** @var array<int, array{0: string, 1: string}> */
    private array $hours;

    /** @var array<string, true> */
    private array $holidays;

    private string $tz;

    public function __construct()
    {
        $this->tz = (string) CrmSettings::get('calendar.timezone');
        $this->hours = [];
        foreach ((array) CrmSettings::get('calendar.hours') as $day => $span) {
            if (is_array($span) && count($span) === 2 && $span[0] < $span[1]) {
                $this->hours[(int) $day] = [$span[0], $span[1]];
            }
        }
        $this->holidays = CrmHoliday::query()->pluck('date')
            ->mapWithKeys(fn ($date) => [\Illuminate\Support\Carbon::parse($date)->toDateString() => true])->all();
    }

    /** The instant $minutes of business time after $from. */
    public function addMinutes(DateTimeInterface $from, int $minutes): CarbonImmutable
    {
        $at = CarbonImmutable::instance($from)->setTimezone($this->tz);
        $left = max(0, $minutes);

        for ($guard = 0; $guard < 800; $guard++) {
            [$open, $close] = $this->window($at);
            if ($open === null || $at >= $close) {
                $at = $this->nextDayStart($at);

                continue;
            }
            if ($at < $open) {
                $at = $open;
            }
            $available = (int) floor(($close->getTimestamp() - $at->getTimestamp()) / 60);
            if ($left <= $available) {
                return $at->addMinutes($left)->utc();
            }
            $left -= $available;
            $at = $this->nextDayStart($at);
        }

        return $at->utc();
    }

    /** Business minutes between two instants (0 when $to is before $from). */
    public function minutesBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $at = CarbonImmutable::instance($from)->setTimezone($this->tz);
        $end = CarbonImmutable::instance($to)->setTimezone($this->tz);
        $total = 0;

        for ($guard = 0; $guard < 800 && $at < $end; $guard++) {
            [$open, $close] = $this->window($at);
            if ($open !== null) {
                $start = $at > $open ? $at : $open;
                $stop = $end < $close ? $end : $close;
                if ($stop > $start) {
                    $total += intdiv($stop->getTimestamp() - $start->getTimestamp(), 60);
                }
            }
            $at = $this->nextDayStart($at);
        }

        return $total;
    }

    public function isBusinessTime(DateTimeInterface $at): bool
    {
        $at = CarbonImmutable::instance($at)->setTimezone($this->tz);
        [$open, $close] = $this->window($at);

        return $open !== null && $at >= $open && $at < $close;
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} opening and closing time on that local day */
    private function window(CarbonImmutable $at): array
    {
        $span = $this->hours[$at->dayOfWeekIso] ?? null;
        if ($span === null || isset($this->holidays[$at->toDateString()])) {
            return [null, null];
        }

        return [$at->setTimeFromTimeString($span[0]), $at->setTimeFromTimeString($span[1])];
    }

    private function nextDayStart(CarbonImmutable $at): CarbonImmutable
    {
        return $at->addDay()->startOfDay();
    }
}
