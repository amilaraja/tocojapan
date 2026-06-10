<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Country;
use App\Models\Page;
use App\Cms\Editor;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class ImportRegulationsTemplate implements PageTemplate
{
    /** Region display order. */
    public const REGIONS = ['Asia', 'Africa', 'Caribbean', 'America', 'Europe', 'Oceania'];

    public static function key(): string
    {
        return 'import-regulations';
    }

    public static function label(): string
    {
        return 'Import regulations — database-driven, by region & country';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            Editor::make('data.intro', 'Intro paragraph'),
        ];
    }

    public static function render(Page $page): View
    {
        $countries = Country::query()
            ->with([
                'importRegulations' => fn ($q) => $q->where('is_active', true)
                    ->orderBy('sort_order')->with('ports'),
                // The country's own active ports — used to set the global
                // destination (first port) from each country popup.
                'ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])
            ->whereHas('importRegulations', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->groupBy('region');

        return view('cms.templates.import-regulations', [
            'page' => $page,
            'countriesByRegion' => $countries,
            'regionOrder' => self::REGIONS,
        ]);
    }
}
