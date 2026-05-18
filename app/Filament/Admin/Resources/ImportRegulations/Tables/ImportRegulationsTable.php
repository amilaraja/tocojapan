<?php

namespace App\Filament\Admin\Resources\ImportRegulations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportRegulationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('country.name')
            ->columns([
                TextColumn::make('country.region')
                    ->label('Region')
                    ->badge()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('ports.name')
                    ->label('Ports')
                    ->badge()
                    ->placeholder('All ports'),
                TextColumn::make('year_restriction')
                    ->label('Age limit')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('time_of_shipment')
                    ->label('Shipment time')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Published')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
