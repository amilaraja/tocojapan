<?php

namespace App\Filament\Admin\Resources\Ports\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PortForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country_id')
                    ->relationship('country', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('unlocode'),
                Select::make('shipping_modes')
                    ->label('Shipping modes supported')
                    ->helperText('Which shipping methods this port can receive.')
                    ->multiple()
                    ->options([
                        'roro' => 'RORO',
                        'container' => 'Container',
                    ]),
                TextInput::make('rate_per_m3')
                    ->required()
                    ->numeric(),
                TextInput::make('insurance_pct')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
