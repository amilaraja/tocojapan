<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestQuotes extends TableWidget
{
    protected static ?string $heading = 'Latest quote requests';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Quote::query()->with(['user', 'vehicle'])->latest()->limit(5))
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
                    ->formatStateUsing(fn (string $state) => Quote::STATUSES[$state] ?? $state),
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
