<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class ShippingScheduleTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'shipping-schedule';
    }

    public static function label(): string
    {
        return 'Shipping schedule — headline + uploaded image';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            RichEditor::make('data.intro')->label('Intro text (optional)')->columnSpanFull(),
            FileUpload::make('data.image')
                ->label('Shipping schedule image')
                ->image()
                ->directory('shipping-schedule')
                ->disk('public')
                ->helperText('Upload the latest shipping schedule photo. Replacing it here updates the page.'),
        ];
    }

    public static function render(Page $page): View
    {
        return view('cms.templates.shipping-schedule', ['page' => $page]);
    }
}
