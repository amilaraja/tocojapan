<?php

namespace App\Filament\Admin\Resources\Vehicles\Schemas;

use App\Models\BodyType;
use App\Models\Make;
use App\Models\VehicleModel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                Tab::make('Basics')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(180)
                            ->columnSpan(2)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('ref_no')->required()->maxLength(50),
                        TextInput::make('slug')->required()->maxLength(220),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'sold' => 'Sold',
                                'reserved' => 'Reserved',
                            ])
                            ->default('draft')
                            ->required(),
                        DateTimePicker::make('published_at'),
                        Select::make('make_id')
                            ->relationship('make', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('vehicle_model_id', null))
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(80)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                                TextInput::make('slug')->required()->maxLength(80),
                                Toggle::make('is_active')->default(true),
                            ])
                            ->createOptionUsing(fn (array $data) => Make::create($data + ['is_active' => true, 'sort_order' => 0])->id),
                        Select::make('vehicle_model_id')
                            ->label('Model')
                            ->options(fn (Get $get) => VehicleModel::query()
                                ->where('make_id', $get('make_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(80)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                                TextInput::make('slug')->required()->maxLength(80),
                            ])
                            ->createOptionUsing(function (array $data, Get $get) {
                                $makeId = $get('make_id');
                                abort_if(! $makeId, 422, 'Pick a make first.');

                                return VehicleModel::create($data + [
                                    'make_id' => $makeId,
                                    'is_active' => true,
                                    'sort_order' => 0,
                                ])->id;
                            })
                            ->disabled(fn (Get $get) => ! $get('make_id'))
                            ->helperText(fn (Get $get) => $get('make_id') ? null : 'Pick a make first to enable model selection / creation.'),
                        Select::make('body_type_id')
                            ->relationship('bodyType', 'name')
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(80)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                                TextInput::make('slug')->required()->maxLength(80),
                                Toggle::make('is_active')->default(true),
                            ])
                            ->createOptionUsing(fn (array $data) => BodyType::create($data + ['is_active' => true, 'sort_order' => 0])->id),
                        TextInput::make('year_first_reg')
                            ->label('Year (first reg.)')
                            ->required()
                            ->numeric()
                            ->minValue(1980)
                            ->maxValue((int) date('Y') + 1),
                    ]),

                Tab::make('Spec')
                    ->columns(3)
                    ->schema([
                        TextInput::make('mileage_km')->label('Mileage (km)')->numeric(),
                        TextInput::make('engine_cc')->label('Engine (cc)')->numeric(),
                        Select::make('fuel')->options([
                            'petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric', 'lpg' => 'LPG',
                        ]),
                        Select::make('transmission')->options([
                            'automatic' => 'Automatic', 'manual' => 'Manual', 'cvt' => 'CVT',
                        ]),
                        Select::make('drive')->options([
                            '2wd' => '2WD', '4wd' => '4WD', 'awd' => 'AWD',
                        ]),
                        Select::make('steering_side')->options([
                            'right' => 'Right (RHD)', 'left' => 'Left (LHD)',
                        ])->default('right')->required(),
                        TextInput::make('exterior_color'),
                        TextInput::make('interior_color'),
                        TextInput::make('doors')->numeric(),
                        TextInput::make('seats')->numeric(),
                        Section::make('Dimensions (cm) — used for M³ shipping calculation')
                            ->columns(4)
                            ->schema([
                                TextInput::make('length_cm')->label('L')->numeric()->live(onBlur: true)
                                    ->afterStateUpdated(self::recalcM3()),
                                TextInput::make('width_cm')->label('W')->numeric()->live(onBlur: true)
                                    ->afterStateUpdated(self::recalcM3()),
                                TextInput::make('height_cm')->label('H')->numeric()->live(onBlur: true)
                                    ->afterStateUpdated(self::recalcM3()),
                                TextInput::make('m3')->label('M³')->numeric()->step('0.0001')
                                    ->helperText('Auto-calculated from L × W × H. Editable.'),
                            ])
                            ->columnSpanFull(),
                    ]),

                Tab::make('Pricing')
                    ->columns(3)
                    ->schema([
                        TextInput::make('price_fob')->label('FOB Price')->numeric()->prefix('$'),
                        Select::make('currency')->options([
                            'USD' => 'USD', 'JPY' => 'JPY', 'EUR' => 'EUR', 'GBP' => 'GBP',
                        ])->default('USD')->required(),
                        Toggle::make('price_on_request')->inline(false),
                        TextInput::make('warranty_period')->columnSpan(2),
                    ]),

                Tab::make('Photos')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->collection('photos')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),

                Tab::make('Description')
                    ->schema([
                        Textarea::make('description')->rows(8)->columnSpanFull(),
                    ]),

                Tab::make('Vehicle options')
                    ->schema(self::vehicleOptionsSchema()),

                Tab::make('SEO')
                    ->schema([
                        Textarea::make('seo')
                            ->helperText('JSON: { title, description, keywords }.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    private static function recalcM3(): \Closure
    {
        return function (Get $get, Set $set): void {
            $l = (float) $get('length_cm');
            $w = (float) $get('width_cm');
            $h = (float) $get('height_cm');
            if ($l > 0 && $w > 0 && $h > 0) {
                $set('m3', round(($l * $w * $h) / 1_000_000, 4));
            }
        };
    }

    /**
     * Render the vehicle-options tab as one Fieldset per group with a
     * Toggle per option. Reads + writes through synthetic feature.{group}.{key}
     * paths backed by accessors on Vehicle::features (JSON).
     */
    private static function vehicleOptionsSchema(): array
    {
        $schema = config('vehicle_features');
        $components = [];
        foreach ($schema as $groupKey => $group) {
            $toggles = [];
            foreach ($group['options'] as $opt) {
                $optKey = $opt['key'];
                $label = $opt['label'];
                $toggles[] = Toggle::make("features.{$groupKey}.{$optKey}")
                    ->label($label)
                    ->inline(false)
                    ->dehydrateStateUsing(fn ($state) => $state ? $label : null)
                    ->afterStateHydrated(function (Toggle $component, $state) {
                        // Persisted format is ['key' => 'Label'] — toggle on if the key exists.
                        $component->state(! empty($state));
                    });
            }

            $components[] = Fieldset::make($group['label'])
                ->columns(3)
                ->schema($toggles)
                ->columnSpanFull();
        }

        return $components;
    }
}
