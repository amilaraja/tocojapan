<?php

namespace App\Modules\JctFeed\Console;

use App\Modules\JctFeed\Domain\JctImages;
use App\Modules\JctFeed\Domain\JctStockFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class JctFeedCommand extends Command
{
    protected $signature = 'jct:feed
        {--warm : Render every photo JCT will fetch, so their first import is fast}
        {--write= : Write the feed to this file (.json or .xml) to check it}';

    protected $description = 'JCT stock feed: show the URLs and counts, warm photos, or write a sample file';

    public function handle(): int
    {
        $vehicles = JctStockFeed::vehicles();
        $token = (string) config('jct_feed.token');
        $base = rtrim((string) config('app.url'), '/').'/feeds/jct/';
        $param = config('jct_feed.token_param');

        $this->line('Cars in feed: '.$vehicles->count().' (cap '.config('jct_feed.max_vehicles').')');
        $this->line('Photos: '.$vehicles->sum(fn ($v) => min($v->getMedia('photos')->count(), (int) config('jct_feed.max_photos'))));
        if ($token === '') {
            $this->warn('JCT_FEED_TOKEN is not set: the feed answers 404 until JCT gives us their token.');
        } else {
            $this->line('JSON: '.$base.'stock.json?'.$param.'=***');
            $this->line('XML:  '.$base.'stock.xml?'.$param.'=***');
        }

        if ($path = $this->option('write')) {
            file_put_contents($path, str_ends_with($path, '.xml') ? JctStockFeed::toXml($vehicles) : JctStockFeed::toJson($vehicles));
            $this->info('Written: '.$path);
        }

        if ($this->option('warm')) {
            $done = 0;
            foreach ($vehicles as $vehicle) {
                foreach ($vehicle->getMedia('photos')->take((int) config('jct_feed.max_photos')) as $media) {
                    JctImages::path($media) && $done++;
                }
            }
            $this->info("Photos ready: {$done}");
        }

        Cache::forget('jct-feed:json');
        Cache::forget('jct-feed:xml');

        return self::SUCCESS;
    }
}
