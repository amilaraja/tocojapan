<?php

namespace App\Cms;

use App\Cms\Templates\AboutUsTemplate;
use App\Cms\Templates\ContactTemplate;
use App\Cms\Templates\CountryLandingTemplate;
use App\Cms\Templates\CustomerReviewsTemplate;
use App\Cms\Templates\DefaultTemplate;
use App\Cms\Templates\FaqTemplate;
use App\Cms\Templates\HomeTemplate;
use App\Cms\Templates\HowToBuyTemplate;
use App\Cms\Templates\ImportRegulationsTemplate;
use App\Cms\Templates\ShippingScheduleTemplate;
use App\Cms\Templates\SparePartsTemplate;

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
            AboutUsTemplate::class,
            FaqTemplate::class,
            SparePartsTemplate::class,
            ImportRegulationsTemplate::class,
            HowToBuyTemplate::class,
            ShippingScheduleTemplate::class,
            CustomerReviewsTemplate::class,
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
