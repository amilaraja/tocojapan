<?php

namespace App\Filament\Admin\Resources\Vehicles\Schemas;

use App\Models\BodyType;
use App\Models\Make;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Html;
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
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name', fn ($query) => $query->orderBy('sort_priority')->orderBy('name'))
                            ->default(fn () => Supplier::ownStockId())
                            ->required()
                            ->live()
                            ->helperText('Toco own stock, or the partner feed this vehicle comes from.'),
                        TextInput::make('supplier_ref')
                            ->label("Supplier's vehicle ID")
                            ->maxLength(64)
                            ->visible(fn (Get $get) => (int) $get('supplier_id') !== Supplier::ownStockId())
                            ->helperText('Links this vehicle to the supplier file. Imports update the vehicle with this ID instead of creating a new one.'),
                        Toggle::make('sync_locked')
                            ->label('Keep my edits (lock against supplier updates)')
                            ->visible(fn (Get $get) => (int) $get('supplier_id') !== Supplier::ownStockId())
                            ->helperText('On: imports leave price, specs and photos as you set them and only delist/relist the vehicle.')
                            ->columnSpan(2),
                        Html::make(fn (?Vehicle $record) => self::supplierSummary($record))
                            ->visible(fn (?Vehicle $record) => $record?->isSupplierStock() ?? false)
                            ->columnSpan(2),
                        TextInput::make('stock_no')
                            ->label('Stock ID')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText("Toco's internal stock reference (e.g. E01888). Shown to customers and stamped on the vehicle photos."),
                        TextInput::make('ref_no')
                            ->label('Reference number')
                            ->maxLength(50)
                            ->helperText('External / auction reference (optional).'),
                        TextInput::make('chassis_number')
                            ->label('Chassis number')
                            ->maxLength(80)
                            ->helperText('Optional. Full VIN / chassis number, shown as-is on the public detail page.'),
                        TextInput::make('model_code')
                            ->label('Model code')
                            ->maxLength(60)
                            ->helperText('Optional. Japanese vehicle model code (e.g. DBA-NV150, GH-Z11).'),
                        TextInput::make('slug')->required()->maxLength(220)->columnSpan(2),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'sold' => 'Sold',
                                'reserved' => 'Reserved',
                                'delisted' => 'Delisted (no longer in supplier stock)',
                            ])
                            ->default('draft')
                            ->required(),
                        DateTimePicker::make('published_at'),
                        Toggle::make('is_featured')
                            ->label('Hot deal')
                            ->helperText('Pin to the Hot Deal carousel on the homepage.')
                            ->columnSpan(2),
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
                        TextInput::make('grade')
                            ->label('Grade / trim')
                            ->maxLength(60)
                            ->helperText('Optional. Trim level e.g. Z, GT, LX.'),
                        TextInput::make('location')
                            ->label('Location')
                            ->maxLength(80)
                            ->default('Tochigi, Japan')
                            ->helperText('Where the vehicle is physically stored. Defaults to "Tochigi, Japan" for new vehicles.'),
                        TextInput::make('year_first_reg')
                            ->label('Registration year')
                            ->required()
                            ->numeric()
                            ->minValue(1980)
                            ->maxValue((int) date('Y') + 1),
                        TextInput::make('registration_month')
                            ->label('Registration month')
                            ->numeric()
                            ->minValue(1)->maxValue(12)
                            ->placeholder('1-12')
                            ->helperText('Optional. 1 (Jan) to 12 (Dec).'),
                        TextInput::make('manufacture_year')
                            ->label('Manufacture year')
                            ->numeric()
                            ->minValue(1980)
                            ->maxValue((int) date('Y') + 1)
                            ->helperText('Optional. Counts for destination-country age-limit rules.'),
                        TextInput::make('manufacture_month')
                            ->label('Manufacture month')
                            ->numeric()
                            ->minValue(1)->maxValue(12)
                            ->placeholder('1-12'),
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
                        TextInput::make('price_fob_discount')
                            ->label('Discount price (FOB)')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Optional. When set, the original price is shown struck through and this is the price the customer pays.')
                            ->lt('price_fob')
                            ->rules(['nullable', 'numeric', 'min:0']),
                        Select::make('currency')->options([
                            'USD' => 'USD', 'JPY' => 'JPY', 'EUR' => 'EUR', 'GBP' => 'GBP',
                        ])->default('USD')->required(),
                        Toggle::make('price_on_request')->inline(false),
                        TextInput::make('warranty_period')->columnSpan(2),
                    ]),

                Tab::make('Photos')
                    ->schema([
                        Html::make(fn (?Vehicle $record) => self::externalPhotosPreview($record))
                            ->visible(fn (?Vehicle $record) => ! empty($record?->external_photos))
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('video')
                            ->label('Walkaround video')
                            ->collection('video')
                            ->disk('public')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                            ->maxSize(102400)
                            ->helperText('Optional. MP4/WebM, up to 100 MB. A play button appears over the first photo on the detail page.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->collection('photos')
                            ->disk('public')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->imageEditor()
                            ->panelLayout('grid')
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

    /** Read-only summary of the supplier data behind a feed vehicle. */
    protected static function supplierSummary(?Vehicle $record): string
    {
        if (! $record) {
            return '';
        }
        $meta = $record->supplier_meta ?? [];
        $yen = fn ($v) => $v ? '¥'.number_format((int) $v) : '—';
        $rows = [
            'Supplier retail price' => $yen($meta['retail_price'] ?? null),
            'Supplier wholesale price' => $yen($meta['wholesale_price'] ?? null),
            'Price used for FOB' => $record->source_price ? $yen($record->source_price).' → $'.number_format((float) $record->price_fob) : 'Price on request',
            'Evaluation score' => e($meta['evaluation_score'] ?? '—'),
            'Yard' => e(trim(($meta['yard_prefecture'] ?? '').' '.($meta['yard_city'] ?? '')) ?: '—'),
            'Last seen in supplier file' => $record->supplier_synced_at?->format('Y-m-d H:i') ?? '—',
            'Delisted' => $record->delisted_at?->format('Y-m-d H:i') ?? '—',
        ];
        $html = '<div class="rounded-lg border border-gray-200 dark:border-white/10 p-3 text-sm"><table class="w-full">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td class="py-0.5 pr-4 text-gray-500">'.e($label).'</td><td class="py-0.5 font-medium">'.$value.'</td></tr>';
        }
        if (! empty($meta['auction_sheet'])) {
            $html .= '<tr><td class="py-0.5 pr-4 text-gray-500">Inspection sheet</td><td class="py-0.5"><a class="text-primary-600 underline" target="_blank" rel="noopener" href="'.e($meta['auction_sheet']).'">Open</a></td></tr>';
        }

        return $html.'</table></div>';
    }

    /** Thumbnails of the supplier-hosted photos used while no photos are uploaded. */
    protected static function externalPhotosPreview(?Vehicle $record): string
    {
        $urls = $record?->externalPhotoUrls() ?? [];
        $html = '<p class="text-sm text-gray-500 mb-2">'.count($urls).' photo(s) from the supplier, shown on the site while no photos are uploaded below. Uploading photos replaces them.</p><div class="flex flex-wrap gap-2">';
        foreach (array_slice($urls, 0, 20) as $url) {
            $html .= '<a href="'.e($url).'" target="_blank" rel="noopener"><img src="'.e($url).'" loading="lazy" style="width:120px;height:90px;object-fit:cover;border-radius:4px"></a>';
        }

        return $html.'</div>';
    }
}
