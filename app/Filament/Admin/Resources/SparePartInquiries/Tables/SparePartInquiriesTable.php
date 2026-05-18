<?php

namespace App\Filament\Admin\Resources\SparePartInquiries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SparePartInquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('model_name')
                    ->label('Vehicle')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('condition')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('shipping_method')
                    ->label('Shipping')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_handled')
                    ->label('Handled')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_handled')
                    ->label('Handled status')
                    ->placeholder('All inquiries')
                    ->trueLabel('Handled')
                    ->falseLabel('Open'),
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
