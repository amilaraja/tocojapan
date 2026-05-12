<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Quotes\QuoteResource;
use App\Models\Order;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class QuotesAndOrders extends TableWidget
{
    protected static ?string $heading = 'Latest activity';

    protected int|string|array $columnSpan = 'full';

    public ?string $activeTab = 'orders';

    protected function getTabs(): array
    {
        return [
            'orders' => 'Orders',
            'quotes' => 'Quotes',
        ];
    }

    public function table(Table $table): Table
    {
        return $this->activeTab === 'quotes' ? $this->quotesTable($table) : $this->ordersTable($table);
    }

    protected function ordersTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->with(['user', 'vehicle'])->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('order_no')->label('Order #')->searchable(),
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('vehicle.title')->label('Vehicle')->limit(40),
                TextColumn::make('amount_usd')->label('USD')->money('USD'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid', 'processing' => 'info',
                        'shipped', 'delivered' => 'success',
                        'cancelled', 'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Order::STATUSES[$state] ?? $state),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }

    protected function quotesTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Quote::query()->with(['user', 'vehicle'])->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('reference')->searchable(),
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('vehicle.title')->label('Vehicle')->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'submitted' => 'gray',
                        'in_progress' => 'warning',
                        'quoted' => 'info',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Quote::STATUSES[$state] ?? $state),
                TextColumn::make('price_quoted')->money(fn ($r) => $r->currency ?? 'USD'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Quote $record): string => QuoteResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
