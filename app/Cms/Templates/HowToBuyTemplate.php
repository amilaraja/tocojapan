<?php

namespace App\Cms\Templates;

use App\Cms\Editor;
use App\Cms\PageTemplate;
use App\Models\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;

class HowToBuyTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'how-to-buy';
    }

    public static function label(): string
    {
        return 'How to Buy — 5-step process page';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            Tabs::make('How to buy content')->columnSpanFull()->tabs([

                Tab::make('Hero')
                    ->schema([
                        TextInput::make('data.eyebrow')->default('5 Easy Steps'),
                        TextInput::make('data.headline_lead')->label('Headline — leading text')->default('How to'),
                        TextInput::make('data.headline_accent')->label('Headline — red accent word')->default('Buy'),
                        TextInput::make('data.subtitle')->label('Subtitle')
                            ->default('Simple steps to get your vehicle from TOCO — from selection to your driveway, with full transparency at every stage.'),
                        Section::make('Hero CTAs')->columnSpanFull()->columns(2)->schema([
                            TextInput::make('data.cta_primary_label')->default('Start at step 1'),
                            TextInput::make('data.cta_primary_url')->default('#step-1'),
                            TextInput::make('data.cta_secondary_label')->default('Browse stock'),
                            TextInput::make('data.cta_secondary_url')->default('/vehicles'),
                        ]),
                        Section::make('Video placeholder (right side of hero)')->columnSpanFull()->schema([
                            FileUpload::make('data.video_thumb')
                                ->label('Thumbnail image')
                                ->disk('public')->directory('how-to-buy')->image()->imageEditor()
                                ->columnSpanFull(),
                            TextInput::make('data.video_url')
                                ->label('Video URL (optional)')
                                ->helperText('YouTube/Vimeo URL. When set, clicking play opens the video in a new tab.'),
                            TextInput::make('data.video_caption')->default('How to Buy from TOCO · 1:30'),
                        ]),
                    ]),

                Tab::make('Step navigator')
                    ->schema([
                        TextInput::make('data.nav_eyebrow')->default('— 5 Easy Steps —'),
                        TextInput::make('data.nav_headline')->default('Your journey to your new vehicle.'),
                    ]),

                Tab::make('Step 1 — Search & Order')
                    ->schema(self::stepFields('1', 'Search & Order')),

                Tab::make('Step 2 — Payment')
                    ->schema(array_merge(
                        self::stepFields('2', 'Payment'),
                        [
                            Section::make('Sub-section: Buy Now')
                                ->columnSpanFull()
                                ->schema([
                                    TextInput::make('data.step_2_subhead_b')->label('Sub-heading')->default("Using \"Buy Now\""),
                                    Editor::make('data.step_2_body_b', 'Body text'),
                                ]),
                        ]
                    )),

                Tab::make('Step 3 — Shipment')
                    ->schema(self::stepFields('3', 'Shipment')),

                Tab::make('Step 4 — Documents')
                    ->schema(array_merge(
                        self::stepFields('4', 'Send Documents'),
                        [
                            Section::make('Checklist')->columnSpanFull()->schema([
                                Repeater::make('data.step_4_checklist')
                                    ->label('Document checklist')
                                    ->schema([TextInput::make('item')->required()])
                                    ->itemLabel(fn (array $state): ?string => $state['item'] ?? null)
                                    ->collapsible()->defaultItems(0)->columnSpanFull(),
                            ]),
                        ]
                    )),

                Tab::make('Step 5 — Receive')
                    ->schema(self::stepFields('5', 'Receive Your Vehicle')),
            ]),
        ];
    }

    /**
     * Build the editable schema for one detail step (reused across all 5 tabs).
     *
     * @return array<int, mixed>
     */
    private static function stepFields(string $num, string $defaultTitle): array
    {
        return [
            TextInput::make("data.step_{$num}_title")->label('Title')->default($defaultTitle),

            Section::make('Body')
                ->columnSpanFull()
                ->schema([
                    TextInput::make("data.step_{$num}_subhead")
                        ->label('Sub-heading (optional, mono red)'),
                    Repeater::make("data.step_{$num}_items")
                        ->label('Numbered list (A, B, C…) — leave empty if not needed')
                        ->schema([
                            TextInput::make('marker')->label('Marker')->placeholder('A')->maxLength(2)->columnSpan(1),
                            Editor::make('body', 'Item text')->columnSpan(4),
                        ])
                        ->columns(5)
                        ->itemLabel(fn (array $state): ?string => trim(($state['marker'] ?? '').' '.strip_tags((string) ($state['body'] ?? ''))) ?: null)
                        ->collapsible()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                    Editor::make("data.step_{$num}_body", 'Free-text body (optional, below the list)'),
                    TextInput::make("data.step_{$num}_callout")
                        ->label('Red callout box (optional)'),
                ]),

            Section::make('Media (left column of the block)')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make("data.step_{$num}_media_type")
                        ->label('Media type')
                        ->options([
                            'image' => 'Photo with optional caption tape',
                            'search_mock' => 'Step 1 vehicle-listing mockup (use for Step 1 only)',
                            'payment' => 'Step 2 payment logos block (use for Step 2 only)',
                            'docs' => 'Step 4 EMS + DHL block (use for Step 4 only)',
                        ])
                        ->default('image'),
                    TextInput::make("data.step_{$num}_media_tape")
                        ->label('Tape caption (for photo type)')
                        ->placeholder('VESSEL · NK ASIAN CARRIER · YOKOHAMA'),
                    Select::make("data.step_{$num}_media_tape_color")
                        ->label('Tape colour')
                        ->options(['navy' => 'Navy (default)', 'green' => 'Green (delivery complete)'])
                        ->default('navy'),
                    FileUpload::make("data.step_{$num}_media_image")
                        ->label('Image')
                        ->disk('public')->directory('how-to-buy')->image()->imageEditor()
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.how-to-buy', ['page' => $page]);
    }
}
