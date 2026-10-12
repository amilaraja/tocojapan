<?php

namespace App\Modules\JctFeed\Http;

use App\Models\Vehicle;
use App\Modules\JctFeed\Domain\JctImages;
use App\Modules\JctFeed\Domain\JctStockFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The URLs JCT's daily import calls: stock file (JSON or XML) and photos. */
class JctFeedController
{
    public function json(Request $request): Response
    {
        $this->authorize($request);

        return $this->feed('json', fn () => JctStockFeed::toJson(), 'application/json; charset=utf-8');
    }

    public function xml(Request $request): Response
    {
        $this->authorize($request);

        return $this->feed('xml', fn () => JctStockFeed::toXml(), 'application/xml; charset=utf-8');
    }

    /** Photos stay public by signed name (JCT appends the name to image_base_url, no token). */
    public function image(string $file): BinaryFileResponse
    {
        $mediaId = JctImages::mediaIdFrom($file);
        $media = $mediaId ? Media::find($mediaId) : null;

        // Only photos of cars the feed currently lists.
        abort_unless(
            $media && $media->collection_name === 'photos' && $media->model_type === (new Vehicle)->getMorphClass()
                && JctStockFeed::stock()->whereKey($media->model_id)->exists(),
            404
        );

        $path = JctImages::path($media);
        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function authorize(Request $request): void
    {
        $token = (string) config('jct_feed.token');
        $given = $request->query(config('jct_feed.token_param'));

        // No token configured = feed is off.
        abort_if($token === '' || ! is_string($given) || ! hash_equals($token, $given), 404);
    }

    private function feed(string $format, callable $build, string $type): Response
    {
        $body = Cache::remember('jct-feed:'.$format, (int) config('jct_feed.cache_seconds'), $build);

        return response($body, 200, [
            'Content-Type' => $type,
            // The token is in the URL: never let LiteSpeed or a proxy keep a copy.
            'Cache-Control' => 'private, no-store',
            'X-LiteSpeed-Cache-Control' => 'no-cache',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
