<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\BodyType;
use App\Models\Make;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\Vehicle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;

class HomeTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'home';
    }

    public static function label(): string
    {
        return 'Homepage — full v5 layout';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            Tabs::make('Home content')->columnSpanFull()->tabs([

                Tab::make('Top bar')
                    ->schema([
                        Repeater::make('data.top_bar_left')
                            ->label('Left items (locale / currency)')
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('url')->placeholder('Optional'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                        Repeater::make('data.top_bar_right')
                            ->label('Right items (live, track, phone)')
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('url')->placeholder('Optional'),
                                Select::make('style')
                                    ->options(['plain' => 'Plain link', 'live' => 'Live red dot indicator'])
                                    ->default('plain'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Hero')
                    ->schema([
                        Section::make('Slider images')
                            ->schema([
                                Repeater::make('data.hero_slides')
                                    ->label('Slides')
                                    ->schema([
                                        FileUpload::make('image')
                                            ->disk('public')->directory('home/hero')
                                            ->image()->imageEditor()->imagePreviewHeight('120')
                                            ->required(),
                                        TextInput::make('alt')->label('Alt text')->maxLength(180),
                                    ])
                                    ->reorderable()->collapsible()->defaultItems(0)
                                    ->itemLabel(fn (array $state): ?string => self::repeaterItemLabel($state, 'home/hero', 'alt'))
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Promo tiles — left column')
                            ->schema([
                                Repeater::make('data.promo_left')
                                    ->schema(self::promoTileSchema())
                                    ->itemLabel(fn (array $state): ?string => self::repeaterItemLabel($state, 'home/promos', 'title'))
                                    ->collapsible()->defaultItems(0)->columnSpanFull(),
                            ]),
                        Section::make('Promo tiles — right column')
                            ->schema([
                                Repeater::make('data.promo_right')
                                    ->schema(self::promoTileSchema())
                                    ->itemLabel(fn (array $state): ?string => self::repeaterItemLabel($state, 'home/promos', 'title'))
                                    ->collapsible()->defaultItems(0)->columnSpanFull(),
                            ]),
                    ]),

                Tab::make('Seasonal strip')
                    ->columns(2)
                    ->schema([
                        Section::make('Top strip (above hero slider)')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                FileUpload::make('data.seasonal.image')
                                    ->label('Banner image (wide, sits above the slider)')
                                    ->disk('public')->directory('home/seasonal')
                                    ->image()->imageEditor()
                                    ->columnSpanFull(),
                                TextInput::make('data.seasonal.tag')->label('Tag (e.g. "Limited time")'),
                                TextInput::make('data.seasonal.text')->label('Headline text')
                                    ->helperText('You can use <strong>bold</strong> markup.'),
                                TextInput::make('data.seasonal.cta_label')->label('CTA label')->default('Shop sale'),
                                TextInput::make('data.seasonal.cta_url')->label('CTA URL (used by both strip and sidebar)'),
                            ]),
                        Section::make('Right-column sidebar banner')
                            ->description('Tall image shown in the right sidebar alongside the Hot Deal and Latest Stock blocks. Leave empty to hide.')
                            ->columnSpanFull()
                            ->schema([
                                FileUpload::make('data.seasonal.sidebar_image')
                                    ->label('Sidebar banner image')
                                    ->disk('public')->directory('home/seasonal')
                                    ->image()->imageEditor()
                                    ->columnSpanFull(),
                                TextInput::make('data.seasonal.sidebar_url')
                                    ->label('Sidebar link URL (optional)')
                                    ->placeholder('Defaults to the CTA URL above if left blank.')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Tab::make('Search panel chips')
                    ->schema([
                        Repeater::make('data.popular_chips')
                            ->label('Popular searches')
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('query_string')
                                    ->required()
                                    ->placeholder('?make=toyota&body_type=suv')
                                    ->helperText('Will be appended after /vehicles'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Why Toco')
                    ->schema([
                        TextInput::make('data.why_kicker')->label('Kicker')->default('Why Toco'),
                        TextInput::make('data.why_headline')->label('Headline')
                            ->default('A trusted partner from auction to your port.'),
                        Repeater::make('data.why_toco')
                            ->label('Feature cards (4 recommended)')
                            ->schema([
                                TextInput::make('num')->label('Number prefix')->placeholder('01')->maxLength(4),
                                TextInput::make('title')->required(),
                                Textarea::make('body')->required()->rows(3),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Stats')
                    ->schema([
                        Section::make('Lead text')->columns(2)->schema([
                            TextInput::make('data.stats.lead_a')->label('Lead part A')
                                ->default('By the numbers,'),
                            TextInput::make('data.stats.lead_b')->label('Lead part B (red accent)')
                                ->default('since 2009.'),
                        ]),
                        Repeater::make('data.stats.items')
                            ->label('Stat cells (4 recommended)')
                            ->schema([
                                TextInput::make('n')->label('Number')->placeholder('14,200')->required(),
                                TextInput::make('unit')->label('Unit')->placeholder('+, /5, %'),
                                TextInput::make('label')->label('Label')->placeholder('Units shipped')->required(),
                            ])
                            ->itemLabel(fn (array $state): ?string => trim(($state['n'] ?? '').' '.($state['label'] ?? '')) ?: null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Testimonials')
                    ->schema([
                        Section::make('Section copy')
                            ->description('Edit individual testimonial cards under Admin → Testimonials.')
                            ->schema([
                                TextInput::make('data.testimonials.kicker')->default('Worldwide deliveries'),
                                TextInput::make('data.testimonials.headline')
                                    ->default('Customers in 90+ countries.'),
                                Textarea::make('data.testimonials.body')->rows(2),
                            ]),
                    ]),

                Tab::make('How to buy')
                    ->schema([
                        TextInput::make('data.how_intro_kicker')->default('How to buy'),
                        TextInput::make('data.how_intro_headline')->default('Simple steps to get your vehicle from TOCO'),
                        Textarea::make('data.how_intro_body')->rows(2),
                        Repeater::make('data.how_steps')
                            ->label('Steps (5 recommended)')
                            ->schema([
                                TextInput::make('num')->required()->placeholder('01')->maxLength(4)->columnSpan(1),
                                Select::make('icon')
                                    ->options(\App\Support\HowToBuyIcons::options())
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('title')->required()->columnSpan(3),
                                Textarea::make('body')
                                    ->label('Body text (below the title)')
                                    ->helperText('Shown on every card. Used by steps without buttons; safely ignored when buttons exist.')
                                    ->rows(2)
                                    ->columnSpan(3),
                                Repeater::make('buttons')
                                    ->label('Buttons (leave empty for steps 3–5)')
                                    ->helperText('When set, the buttons appear below the title instead of, or alongside, the body text.')
                                    ->schema([
                                        TextInput::make('label')->required()->columnSpan(2),
                                        TextInput::make('url')->placeholder('https://… or /vehicles')->required()->columnSpan(2),
                                        Select::make('icon')
                                            ->options(\App\Support\HowToBuyIcons::options())
                                            ->searchable()
                                            ->columnSpan(1),
                                        Select::make('style')
                                            ->options(['solid' => 'Red filled', 'outline' => 'White outline'])
                                            ->default('solid')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpan(3),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => trim(($state['num'] ?? '').' '.($state['title'] ?? '')) ?: null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('CTA strip')
                    ->columns(2)
                    ->schema([
                        TextInput::make('data.cta.kicker')->default('Ready to import?'),
                        TextInput::make('data.cta.headline')
                            ->default('Tell us what you want — we\'ll quote and ship it.')
                            ->columnSpanFull(),
                        Textarea::make('data.cta.body')->rows(2)->columnSpanFull(),
                        TextInput::make('data.cta.button_primary_label')->default('Request a quote'),
                        TextInput::make('data.cta.button_primary_url'),
                        TextInput::make('data.cta.button_secondary_label')->default('Browse stock'),
                        TextInput::make('data.cta.button_secondary_url'),
                    ]),
            ]),
        ];
    }

    public static function render(Page $page): View
    {
        $featured = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'bodyType', 'media'])
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        $makesWithCounts = Make::where('is_active', true)
            ->with('media')
            ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('published_count')->orderBy('name')->limit(12)->get();

        $bodyTypesWithCounts = BodyType::where('is_active', true)
            ->with('media')
            ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('published_count')->orderBy('name')->limit(8)->get();

        return view('home', [
            'content' => $page->data ?? [],
            'featured' => $featured,
            'makesWithCounts' => $makesWithCounts,
            'bodyTypesWithCounts' => $bodyTypesWithCounts,
            'allMakes' => Make::where('is_active', true)
                ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('name')->get(['id', 'slug', 'name']),
            'allBodyTypes' => BodyType::where('is_active', true)
                ->with('media')
                ->withCount(['vehicles as published_count' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('name')->get(),
            'totalPublished' => Vehicle::query()->published()->count(),
            'testimonials' => Testimonial::query()
                ->featured()->with('media')->orderBy('sort_order')->orderByDesc('created_at')->limit(12)->get(),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    /**
     * Repeater item label: prepend the image's basename so users can see
     * which uploaded file a collapsed row holds, alongside the title/alt.
     *
     * @param  array<string, mixed>  $state
     */
    private static function repeaterItemLabel(array $state, string $dir, string $textKey): ?string
    {
        $img = is_string($state['image'] ?? null) ? $state['image'] : null;
        $text = is_string($state[$textKey] ?? null) ? trim($state[$textKey]) : '';
        $bits = [];
        if ($img !== null && $img !== '') {
            $bits[] = '📷 '.basename($img);
        }
        if ($text !== '') {
            $bits[] = $text;
        }

        return $bits ? implode(' — ', $bits) : null;
    }

    private static function promoTileSchema(): array
    {
        return [
            FileUpload::make('image')
                ->label('Image')
                ->image()
                ->imageEditor()
                ->imagePreviewHeight('140')
                ->disk('public')
                ->directory('home/promos')
                ->maxSize(2048)
                ->required()
                ->columnSpanFull(),
            TextInput::make('url')
                ->label('Link URL')
                ->placeholder('https://… or /vehicles?make=toyota')
                ->required(),
            TextInput::make('title')
                ->label('Link title (shown on hover, used for alt text)')
                ->placeholder('Browse kei trucks')
                ->required(),
        ];
    }
}
