<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $activeVehicles = Vehicle::query()->where('status', 'published')->count();
        $draftVehicles = Vehicle::query()->where('status', 'draft')->count();
        $openQuotes = Quote::whereNotIn('status', ['archived', 'declined', 'accepted'])->count();
        $openOrders = Order::query()->whereIn('status', ['pending', 'paid', 'processing', 'shipped'])->count();
        $customers = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'customer'))->count();

        return [
            Stat::make('Active vehicles', number_format($activeVehicles))
                ->description($draftVehicles.' in draft')
                ->descriptionIcon('heroicon-o-pencil-square')
                ->color('success'),

            Stat::make('Open quotes', number_format($openQuotes))
                ->description('Submitted, in progress or quoted')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color($openQuotes > 0 ? 'warning' : 'gray'),

            Stat::make('Open orders', number_format($openOrders))
                ->description('Pending payment through to shipped')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color($openOrders > 0 ? 'info' : 'gray'),

            Stat::make('Customers', number_format($customers))
                ->description('Registered accounts')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
