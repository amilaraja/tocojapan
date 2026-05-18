<?php

namespace App\Filament\Admin\Resources\Redirects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('from_path')
                    ->label('From')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('to_path')
                    ->label('To')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status_code')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 301 ? '301 Permanent' : '302 Temporary'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('hits')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_hit_at')
                    ->label('Last used')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('—'),
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
