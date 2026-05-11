<?php

namespace App\Filament\Admin\Resources\Testimonials\Schemas;

use App\Models\Vehicle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->placeholder('K. Muzinga')
                            ->columnSpan(1),
                        TextInput::make('country')
                            ->required()
                            ->placeholder('Congo')
                            ->columnSpan(1),
                        TextInput::make('flag')
                            ->label('Flag emoji')
                            ->placeholder('🇨🇩')
                            ->maxLength(8)
                            ->columnSpan(1),
                    ]),

                Section::make('Photo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photo')
                            ->collection('photo')
                            ->image()
                            ->imageEditor()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Story')
                    ->columns(2)
                    ->schema([
                        Select::make('stars')
                            ->options([5 => '★★★★★', 4 => '★★★★☆', 3 => '★★★☆☆', 2 => '★★☆☆☆', 1 => '★☆☆☆☆'])
                            ->default(5)
                            ->required(),
                        Select::make('vehicle_id')
                            ->label('Linked vehicle (optional)')
                            ->options(fn () => Vehicle::orderByDesc('created_at')
                                ->limit(200)
                                ->get()
                                ->mapWithKeys(fn ($v) => [$v->id => trim(($v->ref_no ?? '').' · '.($v->title ?? ''))])
                                ->all())
                            ->searchable()
                            ->nullable(),
                        Textarea::make('quote')
                            ->placeholder('Toyota Vanguard arrived exactly as promised — paperwork was sorted before the vessel docked.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Visibility')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('Show on homepage')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower = appears first.'),
                    ]),
            ]);
    }
}
