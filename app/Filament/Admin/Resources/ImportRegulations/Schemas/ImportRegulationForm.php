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
                    ->label('Age limit (display text)')
                    ->placeholder('e.g. Under 5 years / No restriction')
                    ->helperText('Free-text shown to buyers. Set the numeric version on the right for YOM comparison.')
                    ->maxLength(120),
                TextInput::make('year_max_age')
                    ->label('Age limit (years — numeric)')
                    ->placeholder('e.g. 5')
                    ->helperText('Max vehicle age (years) accepted by customs. Used to flag too-old stock against this country. Leave blank for "no limit".')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(40),
                Select::make('steering_restriction')
                    ->label('Steering side allowed')
                    ->options([
                        '' => 'Any (no restriction)',
                        'rhd_only' => 'Right-hand drive only (RHD)',
                        'lhd_only' => 'Left-hand drive only (LHD)',
                    ])
                    ->default('')
                    ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state),
                TextInput::make('inspection')
                    ->label('Pre-shipment inspection')
                    ->placeholder('JEVIC / JAAI / VTA + JEVIC / Not required')
                    ->helperText('Free-text — wording varies by country.')
                    ->maxLength(120),
                TextInput::make('time_of_shipment')
                    ->label('Shipment time')
                    ->placeholder('e.g. 14–21 days')
                    ->maxLength(120),
                TextInput::make('other_restrictions')
                    ->label('Other restrictions (comma-separated tags)')
                    ->placeholder('container only, ULEZ, no diesel before 2013')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('comments')
                    ->label('Long description (Import Regulations page)')
                    ->helperText('Full paragraphs shown on the per-country regulations page.')
                    ->rows(6)
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
