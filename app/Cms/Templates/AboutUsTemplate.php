<?php

namespace App\Cms\Templates;

use App\Cms\Editor;
use App\Cms\PageTemplate;
use App\Models\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;

class AboutUsTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'about-us';
    }

    public static function label(): string
    {
        return 'About us — full company page';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            Tabs::make('About us content')->columnSpanFull()->tabs([

                Tab::make('Page title')
                    ->schema([
                        TextInput::make('data.eyebrow')->label('Eyebrow')->default('About Toco'),
                        TextInput::make('data.headline')->label('Headline')->default('Quality Japanese vehicles, since 2004.'),
                        TextInput::make('data.subtitle')->label('Subtitle')
                            ->default('Established in Sano City, Tochigi — exporting to 90+ countries with honesty, trust, and 20+ years of reliable service.'),
                    ]),

                Tab::make('Overview')
                    ->schema([
                        Section::make('Copy')->columnSpanFull()->schema([
                            TextInput::make('data.overview_eyebrow')->default('Company overview'),
                            TextInput::make('data.overview_headline')->default('Trusted Japanese vehicle exporter since 2004.'),
                            Editor::make('data.overview_body', 'Body paragraphs')
                                ->helperText('Rich text. Use <strong> for emphasis.'),
                        ]),
                        Section::make('Lead image')->columnSpanFull()->columns(2)->schema([
                            FileUpload::make('data.overview_image')
                                ->label('Office image')
                                ->disk('public')->directory('about')
                                ->image()->imageEditor()
                                ->columnSpanFull(),
                            TextInput::make('data.overview_image_caption')
                                ->label('Image caption')
                                ->default('Toco HQ · Sano City, Tochigi')
                                ->columnSpan(2),
                        ]),
                        Section::make('Quick stats (4 cells)')->columnSpanFull()->schema([
                            Repeater::make('data.quick_stats')
                                ->schema([
                                    TextInput::make('n')->label('Number')->required()->placeholder('2004'),
                                    TextInput::make('suffix')->label('Suffix (red)')->placeholder('+ yrs'),
                                    TextInput::make('l')->label('Label')->required()->placeholder('Established'),
                                ])
                                ->columns(3)
                                ->itemLabel(fn (array $state): ?string => trim(($state['n'] ?? '').' '.($state['l'] ?? '')) ?: null)
                                ->collapsible()
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Tab::make('Commitment')
                    ->schema([
                        TextInput::make('data.commitment_eyebrow')->default('Our commitment'),
                        TextInput::make('data.commitment_headline')->default('Built on honesty, trust, and reliable service.'),
                        TextInput::make('data.commitment_body')
                            ->default("Four principles that have guided every shipment we've made for over two decades."),
                        Repeater::make('data.commitment_items')
                            ->label('Commitment cells (4 recommended)')
                            ->schema([
                                TextInput::make('num')->required()->placeholder('01')->maxLength(4)->columnSpan(1),
                                TextInput::make('t')->label('Title')->required()->columnSpan(2),
                                Editor::make('b', 'Body')->columnSpan(3),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => trim(($state['num'] ?? '').' '.($state['t'] ?? '')) ?: null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Brands')
                    ->schema([
                        TextInput::make('data.brands_eyebrow')->default('Brands we deal in'),
                        TextInput::make('data.brands_headline')->default('Every major Japanese manufacturer.'),
                        TextInput::make('data.brands_body')
                            ->default('Direct sourcing from authorised dealers, major auctions, and trusted suppliers across Japan.'),
                        Repeater::make('data.brands')
                            ->label('Brand tiles')
                            ->schema([
                                TextInput::make('name')->required()->columnSpan(1),
                            ])
                            ->columns(1)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('What we export')
                    ->schema([
                        TextInput::make('data.categories_eyebrow')->default('What we export'),
                        TextInput::make('data.categories_headline')->default('From kei trucks to commercial fleets.'),
                        Repeater::make('data.categories')
                            ->label('Categories')
                            ->schema([
                                TextInput::make('label')->required(),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Gallery')
                    ->schema([
                        TextInput::make('data.gallery_eyebrow')->default('Visit us'),
                        TextInput::make('data.gallery_headline')->default('Inside our office in Sano City, Tochigi.'),
                        TextInput::make('data.gallery_body')
                            ->default('Come and see our team and showroom in person. Visitors are welcome — please book ahead so we can prepare.'),
                        Repeater::make('data.gallery_items')
                            ->label('Gallery images (first one is wide)')
                            ->schema([
                                FileUpload::make('image')->disk('public')->directory('about/gallery')->image()->imageEditor()->required(),
                                TextInput::make('caption')->required(),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['caption'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),

                Tab::make('Company details')
                    ->schema([
                        TextInput::make('data.info_eyebrow')->default('Company details'),
                        TextInput::make('data.info_headline')->default('Registered company information.'),
                        Repeater::make('data.info_rows')
                            ->label('Rows (label + value)')
                            ->schema([
                                TextInput::make('k')->label('Label')->required()->columnSpan(1),
                                TextInput::make('v')->label('Value')->required()->columnSpan(2),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => $state['k'] ?? null)
                            ->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),
            ]),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.about-us', ['page' => $page]);
    }
}
