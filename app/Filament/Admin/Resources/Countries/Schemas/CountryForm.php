<?php

namespace App\Filament\Admin\Resources\Countries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('iso2')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('currency_code'),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('pre_inspection_required')
                    ->label('Pre-inspection mandatory')
                    ->helperText('When on, the Pre-inspection Fee in the CIF estimator is force-ticked for buyers shipping to this country.'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
