<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Page;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class DefaultTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'default';
    }

    public static function label(): string
    {
        return 'Default — title + body';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60)->helperText('Small uppercase line above the headline.'),
            RichEditor::make('data.body')->label('Body')->columnSpanFull(),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.default', ['page' => $page]);
    }
}
