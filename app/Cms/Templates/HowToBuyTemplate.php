<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use App\Cms\Editor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Contracts\View\View;

class HowToBuyTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'how-to-buy';
    }

    public static function label(): string
    {
        return 'How to Buy — numbered step cards';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            Repeater::make('data.steps')
                ->label('Process steps')
                ->schema([
                    TextInput::make('title')->label('Step title')->required(),
                    Textarea::make('description')->label('Description')->rows(3)->required(),
                    FileUpload::make('icon')
                        ->label('Step icon (circular badge)')
                        ->image()
                        ->directory('how-to-buy')
                        ->disk('public'),
                ])
                ->defaultItems(0)
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
            Editor::make('data.intro', 'Intro text (below the steps)'),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.how-to-buy', ['page' => $page]);
    }
}
