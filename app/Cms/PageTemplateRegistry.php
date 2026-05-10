<?php

namespace App\Cms;

use App\Cms\Templates\ContactTemplate;
use App\Cms\Templates\CountryLandingTemplate;
use App\Cms\Templates\DefaultTemplate;
use App\Cms\Templates\FaqTemplate;
use App\Cms\Templates\HomeTemplate;

class PageTemplateRegistry
{
    /**
     * @return array<int, class-string<PageTemplate>>
     */
    public static function all(): array
    {
        return [
            HomeTemplate::class,
            DefaultTemplate::class,
            CountryLandingTemplate::class,
            ContactTemplate::class,
            FaqTemplate::class,
        ];
    }

    /**
     * @return array<string, string> key => label
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $cls) {
            $out[$cls::key()] = $cls::label();
        }

        return $out;
    }

    /** @return class-string<PageTemplate>|null */
    public static function resolve(string $key): ?string
    {
        foreach (self::all() as $cls) {
            if ($cls::key() === $key) {
                return $cls;
            }
        }

        return null;
    }
}
