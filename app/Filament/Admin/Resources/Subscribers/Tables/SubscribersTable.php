<?php

namespace App\Filament\Admin\Resources\Subscribers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->label('Email')->searchable()->sortable()->copyable(),
                TextColumn::make('source')->label('Source')->badge()->color('info')->sortable(),
                TextColumn::make('created_at')->label('Subscribed')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('unsubscribed_at')->label('Unsubscribed')->dateTime('Y-m-d H:i')->sortable()->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')->label('User agent')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
