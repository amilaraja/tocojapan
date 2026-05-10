<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class FaqTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'faq';
    }

    public static function label(): string
    {
        return 'FAQ — grouped Q&A';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            Repeater::make('data.groups')
                ->label('FAQ groups')
                ->schema([
                    TextInput::make('title')->label('Group title')->required(),
                    Repeater::make('items')
                        ->label('Questions')
                        ->schema([
                            TextInput::make('question')->required(),
                            Textarea::make('answer')->rows(3)->required(),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                        ->collapsible()
                        ->defaultItems(1),
                ])
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->collapsible()
                ->defaultItems(1)
                ->columnSpanFull(),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.faq', ['page' => $page]);
    }
}
