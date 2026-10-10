<?php

namespace App\Modules\PartnerApi\Filament\Widgets;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Filament\Resources\Holds\VehicleHoldResource;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use App\Modules\PartnerApi\Support\PartnerAccess;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard: active holds and partner traffic today (UI brief 6.2). */
class PartnerNetworkStats extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Partner network';

    public static function canView(): bool
    {
        return PartnerAccess::canAdmin();
    }

    protected function getStats(): array
    {
        $start = CarbonImmutable::now(config('partner_api.timezone'))->startOfDay()->utc();
        $today = PartnerRequestLog::where('created_at', '>=', $start);
        $active = VehicleHold::active()->count();
        $soon = VehicleHold::active()->whereNotNull('expires_at')->where('expires_at', '<=', now()->addHours(6))->count();

        return [
            Stat::make('Cars on hold', number_format($active))
                ->description($soon > 0 ? $soon.' ending within 6 hours' : 'None ending soon')
                ->color($soon > 0 ? 'warning' : 'gray')
                ->url(VehicleHoldResource::getUrl('index')),
            Stat::make('Partner calls today', number_format((clone $today)->where('kind', 'api')->count())),
            Stat::make('Photos sent today', number_format((clone $today)->where('kind', 'image')->where('status', 200)->count())),
            Stat::make('Partner errors today', number_format((clone $today)->where('status', '>=', 400)->count()))
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
