<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FavoritesRelationManager extends RelationManager
{
    protected static string $relationship = 'favorites';

    protected static ?string $title = 'Saved vehicles';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('vehicle.ref_no')->label('Ref'),
                TextColumn::make('vehicle.title')->label('Vehicle')->limit(50),
                TextColumn::make('vehicle.price_fob')
                    ->label('Price')
                    ->money(fn ($r) => $r->vehicle?->currency ?? 'USD')
                    ->getStateUsing(fn ($r) => $r->vehicle?->effectivePriceFob() ?? $r->vehicle?->price_fob),
                TextColumn::make('created_at')->label('Saved')->dateTime('Y-m-d')->sortable(),
            ]);
    }
}
