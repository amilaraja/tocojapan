<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Page;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class ContactTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'contact';
    }

    public static function label(): string
    {
        return 'Contact — intro + details + form';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            RichEditor::make('data.intro')->label('Intro paragraph')->columnSpanFull(),
            TextInput::make('data.address_line_1')->label('Address line 1'),
            TextInput::make('data.address_line_2')->label('Address line 2'),
            TextInput::make('data.map_embed_url')
                ->label('Google Maps embed URL')
                ->url()
                ->helperText('Paste the iframe `src` from Google Maps Share > Embed.'),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.contact', [
            'page' => $page,
            'general' => app(GeneralSettings::class),
        ]);
    }
}
