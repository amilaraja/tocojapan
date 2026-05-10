<?php

namespace App\Filament\Admin\Resources\Quotes\Schemas;

use App\Models\Quote;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Quote')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reference')->disabled()->dehydrated(false),
                        Select::make('status')
                            ->options(Quote::STATUSES)
                            ->required(),
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('vehicle_id')
                            ->relationship('vehicle', 'title')
                            ->searchable(),
                        Select::make('country_id')
                            ->relationship('country', 'name')
                            ->searchable(),
                        Select::make('port_id')
                            ->relationship('port', 'name')
                            ->searchable(),
                    ]),
                Tab::make('Pricing')
                    ->columns(3)
                    ->schema([
                        TextInput::make('price_quoted')->prefix('$')->numeric(),
                        TextInput::make('cif_total')->prefix('$')->numeric()->helperText('Final landed cost incl. freight + insurance.'),
                        TextInput::make('currency')->default('USD')->maxLength(3)->required(),
                        DatePicker::make('valid_until')->columnSpan(2),
                    ]),
                Tab::make('Contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')->required(),
                        TextInput::make('contact_email')->email()->required(),
                        TextInput::make('contact_phone'),
                    ]),
                Tab::make('Internal')
                    ->schema([
                        Section::make('Hidden from the customer')
                            ->schema([
                                Textarea::make('internal_notes')->rows(8)->columnSpanFull(),
                            ]),
                    ]),
            ]),
        ]);
    }
}
