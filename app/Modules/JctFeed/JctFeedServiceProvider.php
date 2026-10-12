<?php

namespace App\Modules\JctFeed;

use App\Modules\JctFeed\Console\JctFeedCommand;
use App\Modules\JctFeed\Domain\JctImages;
use App\Modules\JctFeed\Http\JctFeedController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * JapaneseCarTrade.com stock import feed. Stand-alone: shares nothing with
 * the Partner Stock API beyond the vehicles it reads. Hooks into shared code
 * are listed in docs/jct-feed/README.md.
 */
class JctFeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/jct_feed.php', 'jct_feed');
    }

    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            // No web middleware: no session or cookies for a machine pull.
            Route::prefix('feeds/jct')->name('jct-feed.')->group(function () {
                Route::get('stock.json', [JctFeedController::class, 'json'])->name('json');
                Route::get('stock.xml', [JctFeedController::class, 'xml'])->name('xml');
                Route::get('images/{file}', [JctFeedController::class, 'image'])->where('file', '[0-9a-f\-]+\.jpg')->name('image');
            });
        }

        Media::deleted(fn (Media $media) => JctImages::forget($media->id));

        if ($this->app->runningInConsole()) {
            $this->commands([JctFeedCommand::class]);
        }
    }
}
