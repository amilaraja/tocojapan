<?php

namespace App\Filament\Admin\Resources\VehicleOptions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('price')
                    ->numeric()->prefix('$')->minValue(0)
                    ->helperText('Leave blank for "ASK" (sales follows up after the buyer ticks it).'),
                TextInput::make('tooltip')
                    ->maxLength(255)
                    ->helperText('Optional. Shown on hover next to the option name.'),
                Toggle::make('is_active')->default(true),
                TextInput::make('sort_order')->required()->numeric()->default(0),
            ]);
    }
}
