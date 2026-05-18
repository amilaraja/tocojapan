<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Country;
use App\Models\Page;
use App\Models\Vehicle;
use App\Cms\Editor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\View\View;

class CountryLandingTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'country-landing';
    }

    public static function label(): string
    {
        return 'Country landing — auto-injects ports + popular vehicles';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            Select::make('data.country_id')
                ->label('Destination country')
                ->options(fn () => Country::orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            TextInput::make('data.headline')->label('Headline')->maxLength(180)
                ->helperText('e.g. "Japanese cars to Sri Lanka"'),
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            Editor::make('data.intro', 'Intro paragraph'),
            Section::make('Body sections')
                ->schema([
                    Editor::make('data.body', 'Body'),
                ]),
        ];
    }

    public static function render(Page $page): View
    {
        $countryId = (int) ($page->data['country_id'] ?? 0);
        $country = Country::with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->find($countryId);

        $popularVehicles = Vehicle::query()
            ->published()
            ->with(['make', 'vehicleModel', 'media'])
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        return view('cms.templates.country-landing', [
            'page' => $page,
            'country' => $country,
            'popularVehicles' => $popularVehicles,
        ]);
    }
}
