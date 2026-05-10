<?php

namespace App\Filament\Admin\Resources\Vehicles\Tables;

use App\Models\BodyType;
use App\Models\Make;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ref_no')->searchable()->sortable()->toggleable(),
                TextColumn::make('title')->searchable()->limit(40)->toggleable(),
                TextColumn::make('make.name')->label('Make')->sortable()->toggleable(),
                TextColumn::make('vehicleModel.name')->label('Model')->sortable()->toggleable(),
                TextColumn::make('year_first_reg')->label('Year')->numeric()->sortable()->toggleable(),
                TextColumn::make('mileage_km')->label('Mileage')->numeric()->sortable()->toggleable(),
                TextColumn::make('m3')->label('M³')->numeric(decimalPlaces: 3)->sortable()->toggleable(),
                TextColumn::make('price_fob')->label('FOB')->money(fn ($record) => $record->currency)->sortable()->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'sold' => 'danger',
                        'reserved' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('published_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('price_on_request')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'sold' => 'Sold',
                    'reserved' => 'Reserved',
                ]),
                SelectFilter::make('make_id')
                    ->label('Make')
                    ->options(fn () => Make::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('body_type_id')
                    ->label('Body type')
                    ->options(fn () => BodyType::orderBy('name')->pluck('name', 'id')->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
