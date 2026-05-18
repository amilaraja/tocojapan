<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Country;
use App\Models\Page;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class SparePartsTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'spare-parts';
    }

    public static function label(): string
    {
        return 'Spare parts — intro + order form';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            RichEditor::make('data.body')->label('Intro / ordering steps')->columnSpanFull(),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.spare-parts', [
            'page' => $page,
            'countries' => Country::orderBy('name')->pluck('name')->all(),
        ]);
    }
}
