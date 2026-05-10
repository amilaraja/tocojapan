<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Quote;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $publishedVehicles = Vehicle::query()->where('status', 'published')->count();
        $draftVehicles = Vehicle::query()->where('status', 'draft')->count();
        $openQuotes = Quote::whereNotIn('status', ['archived', 'declined', 'accepted'])->count();
        $customers = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'customer'))->count();
        $quotedSum = (float) Quote::whereIn('status', ['quoted', 'accepted'])->sum('price_quoted');

        return [
            Stat::make('Published vehicles', number_format($publishedVehicles))
                ->description($draftVehicles.' in draft')
                ->descriptionIcon('heroicon-o-pencil-square')
                ->color('success'),

            Stat::make('Open quotes', number_format($openQuotes))
                ->description('Submitted, in progress or quoted')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color($openQuotes > 0 ? 'warning' : 'gray'),

            Stat::make('Customers', number_format($customers))
                ->description('Registered accounts')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('info'),

            Stat::make('Quoted total', '$'.number_format($quotedSum, 0))
                ->description('Sum of accepted + quoted prices')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
