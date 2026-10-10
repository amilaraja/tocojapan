<?php

namespace App\Modules\PartnerApi\Domain\Usage;

use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Usage figures from the request log, for GET /usage (FR-USE-01) and the
 * admin Usage page (UI brief section 4). Days are Toco business days.
 */
class UsageReport
{
    private function __construct(private Partner $partner) {}

    public static function for(Partner $partner): self
    {
        return new self($partner);
    }

    public function errorsToday(): int
    {
        return PartnerRequestLog::where('partner_id', $this->partner->id)
            ->where('created_at', '>=', $this->dayStart(0))->where('status', '>=', 400)->count();
    }

    /** Last successful vehicle list call: when the partner last synced. */
    public function lastSyncAt(): ?Carbon
    {
        $at = PartnerRequestLog::where('partner_id', $this->partner->id)
            ->where('endpoint', 'partner.vehicles.index')->whereIn('status', [200, 304])->max('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    /** @return list<array{date: string, requests: int, images: int, errors: int}> oldest first */
    public function days(int $count): array
    {
        $tz = config('partner_api.timezone');
        $rows = PartnerRequestLog::where('partner_id', $this->partner->id)
            ->where('created_at', '>=', $this->dayStart($count - 1))
            ->get(['kind', 'status', 'counted', 'created_at']);

        $byDay = $rows->groupBy(fn ($row) => $row->created_at->copy()->timezone($tz)->toDateString());

        return collect(range($count - 1, 0))->map(function (int $back) use ($byDay, $tz) {
            $date = CarbonImmutable::now($tz)->subDays($back)->toDateString();
            $day = $byDay->get($date, collect());

            return [
                'date' => $date,
                'requests' => $day->where('kind', 'api')->where('counted', true)->count(),
                'images' => $day->where('kind', 'image')->where('status', 200)->count(),
                'errors' => $day->where('status', '>=', 400)->count(),
            ];
        })->all();
    }

    private function dayStart(int $daysBack): CarbonImmutable
    {
        return CarbonImmutable::now(config('partner_api.timezone'))->subDays($daysBack)->startOfDay()->utc();
    }
}
