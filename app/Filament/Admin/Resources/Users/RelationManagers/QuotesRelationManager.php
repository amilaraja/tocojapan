<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Models\Quote;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    protected static ?string $title = 'Quote requests';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable(),
                TextColumn::make('vehicle.title')->limit(40),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Quote::STATUSES[$state] ?? $state),
                TextColumn::make('price_quoted')->money(fn ($r) => $r->currency ?? 'USD'),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable(),
            ]);
    }
}
