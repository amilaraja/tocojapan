<?php

namespace App\Cms;

use App\Models\Page;
use Illuminate\Contracts\View\View;

interface PageTemplate
{
    /** Stable identifier stored in pages.template_key. */
    public static function key(): string;

    /** Human-readable label for the Filament select. */
    public static function label(): string;

    /**
     * Filament schema components for the "Content" tab.
     *
     * @return array<int, mixed>
     */
    public static function fields(): array;

    /** Render the public-facing view for a published Page. */
    public static function render(Page $page): View;
}
