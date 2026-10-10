<?php

namespace App\Modules\PartnerApi\Filament\Resources\Holds\Pages;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Filament\Resources\Holds\VehicleHoldResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListHolds extends ListRecords
{
    protected static string $resource = VehicleHoldResource::class;

    protected ?string $heading = 'Holds';

    public function getSubheading(): ?string
    {
        $active = VehicleHold::active()->count();
        $soon = VehicleHold::active()->whereNotNull('expires_at')->where('expires_at', '<=', now()->addHours(6))->count();

        return "{$active} on hold · {$soon} expiring within 6 hours";
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', VehicleHold::STATE_ACTIVE))
                ->badge(fn () => VehicleHold::active()->count() ?: null),
            'recent' => Tab::make('Recent')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30))),
            'all' => Tab::make('All'),
        ];
    }
}
