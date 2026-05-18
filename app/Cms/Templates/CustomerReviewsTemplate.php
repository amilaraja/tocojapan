<?php

namespace App\Cms\Templates;

use App\Cms\PageTemplate;
use App\Models\Page;
use App\Models\Testimonial;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class CustomerReviewsTemplate implements PageTemplate
{
    public static function key(): string
    {
        return 'customer-reviews';
    }

    public static function label(): string
    {
        return 'Customer reviews — database-driven testimonials grid';
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('data.kicker')->label('Kicker')->maxLength(60),
            TextInput::make('data.headline')->label('Headline')->maxLength(180),
            RichEditor::make('data.intro')->label('Intro paragraph')->columnSpanFull(),
        ];
    }

    public static function render(Page $page): View
    {
        $testimonials = Testimonial::query()
            ->published()
            ->with('media')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Aggregate across every published review for the schema.org markup.
        $reviewCount = Testimonial::query()->published()->count();
        $avgRating = $reviewCount > 0
            ? round((float) Testimonial::query()->published()->avg('stars'), 1)
            : null;

        return view('cms.templates.customer-reviews', [
            'page' => $page,
            'testimonials' => $testimonials,
            'reviewCount' => $reviewCount,
            'avgRating' => $avgRating,
        ]);
    }
}
