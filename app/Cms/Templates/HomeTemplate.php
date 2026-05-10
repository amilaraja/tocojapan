<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Page;
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
                            ->itemLabel(fn (array $s): ?string => $s['label'] ?? null)
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
                            ->itemLabel(fn (array $s): ?string => $s['label'] ?? null)
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
                                            ->image()->imageEditor()
                                            ->required(),
                                        TextInput::make('alt')->label('Alt text')->maxLength(180),
                                    ])
                                    ->reorderable()->collapsible()->defaultItems(0)
                                    ->itemLabel(fn (array $s): ?string => $s['alt'] ?? null)
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Promo tiles — left column')
                            ->schema([
                                Repeater::make('data.promo_left')
                                    ->schema(self::promoTileSchema())
                                    ->itemLabel(fn (array $s): ?string => $s['title'] ?? null)
                                    ->collapsible()->defaultItems(0)->columnSpanFull(),
                            ]),
                        Section::make('Promo tiles — right column')
                            ->schema([
                                Repeater::make('data.promo_right')
                                    ->schema(self::promoTileSchema())
                                    ->itemLabel(fn (array $s): ?string => $s['title'] ?? null)
                                    ->collapsible()->defaultItems(0)->columnSpanFull(),
                            ]),
                    ]),

                Tab::make('Seasonal strip')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('data.seasonal.image')
                            ->label('Banner image')
                            ->disk('public')->directory('home/seasonal')
                            ->image()->imageEditor(),
                        TextInput::make('data.seasonal.tag')->label('Tag (e.g. "Limited time")'),
                        TextInput::make('data.seasonal.text')->label('Headline text')->columnSpanFull()
                            ->helperText('You can use <strong>bold</strong> markup.'),
                        TextInput::make('data.seasonal.cta_label')->label('CTA label')->default('Shop sale'),
                        TextInput::make('data.seasonal.cta_url')->label('CTA URL'),
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
                            ->itemLabel(fn (array $s): ?string => $s['label'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Why Toco')
                    ->schema([
                        Repeater::make('data.why_toco')
                            ->label('Feature cards (4 recommended)')
                            ->schema([
                                TextInput::make('num')->label('Number prefix')->placeholder('01')->maxLength(4),
                                TextInput::make('title')->required(),
                                Textarea::make('body')->required()->rows(3),
                            ])
                            ->itemLabel(fn (array $s): ?string => $s['title'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('How it works')
                    ->schema([
                        TextInput::make('data.how_intro_kicker')->default('How it works'),
                        TextInput::make('data.how_intro_headline')->default('Four steps from browse to delivery.'),
                        Textarea::make('data.how_intro_body')->rows(2),
                        Repeater::make('data.how_steps')
                            ->label('Steps (4 recommended)')
                            ->schema([
                                TextInput::make('num')->required()->placeholder('01')->maxLength(4),
                                TextInput::make('title')->required(),
                                Textarea::make('body')->required()->rows(2),
                            ])
                            ->itemLabel(fn (array $s): ?string => $s['title'] ?? null)
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
        return view('home', ['content' => $page->data ?? []]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function promoTileSchema(): array
    {
        return [
            Select::make('tone')
                ->options(['red' => 'Red', 'navy' => 'Navy', 'silver' => 'Silver'])
                ->default('navy')
                ->required(),
            TextInput::make('title')->required(),
            TextInput::make('sub')->label('Sub-text (mono)')->placeholder('660cc · RHD'),
            TextInput::make('url')->placeholder('Optional'),
        ];
    }
}
