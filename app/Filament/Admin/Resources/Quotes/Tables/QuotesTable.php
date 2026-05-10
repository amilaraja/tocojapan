<?php

namespace App\Filament\Admin\Resources\Quotes\Tables;

use App\Models\Quote;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class QuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('vehicle.title')->label('Vehicle')->searchable()->limit(40),
                TextColumn::make('country.name')->label('Country')->toggleable(),
                TextColumn::make('port.name')->label('Port')->toggleable(),
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
                TextColumn::make('price_quoted')->money(fn ($r) => $r->currency)->sortable(),
                TextColumn::make('cif_total')->money(fn ($r) => $r->currency)->sortable()->toggleable(),
                TextColumn::make('valid_until')->date()->sortable()->toggleable(),
                TextColumn::make('last_customer_reply_at')->label('Last reply')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Quote::STATUSES),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('archive')
                        ->label('Archive selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            $records->toQuery()->update(['status' => 'archived']);
                            Notification::make()->title("Archived {$count} quote(s).")->success()->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
