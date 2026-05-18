<?php

namespace App\Filament\Admin\Resources\ImportRegulations\Schemas;

use App\Models\Port;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ImportRegulationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country_id')
                    ->label('Destination country')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                TextInput::make('short_description')
                    ->label('Short description')
                    ->helperText('A brief one-line summary shown with this rule on the front-end.')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('ports')
                    ->label('Destination ports')
                    ->helperText('Pick the ports this rule applies to. Leave empty to apply to all ports of the country.')
                    ->relationship('ports', 'name')
                    ->multiple()
                    ->preload()
                    ->options(fn (callable $get) => $get('country_id')
                        ? Port::where('country_id', $get('country_id'))->orderBy('name')->pluck('name', 'id')->all()
                        : [])
                    ->columnSpanFull(),
                TextInput::make('year_restriction')
                    ->label('Age limit')
                    ->placeholder('e.g. Under 5 years / No restriction')
                    ->maxLength(120),
                TextInput::make('time_of_shipment')
                    ->label('Shipment time')
                    ->placeholder('e.g. 14–21 days')
                    ->maxLength(120),
                Textarea::make('comments')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Published')
                    ->default(true),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
