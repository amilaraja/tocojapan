<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Vehicle;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    /**
     * /sitemap.xml — the index pointing at per-type sub-sitemaps.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.index.xml', now()->addHour(), function (): string {
            $entries = [
                self::sitemapEntry(self::abs(route('sitemap.static', absolute: false)), now()),
                self::sitemapEntry(self::abs(route('sitemap.pages', absolute: false)),
                    Page::query()->max('updated_at')),
                self::sitemapEntry(self::abs(route('sitemap.vehicles', absolute: false)),
                    Vehicle::query()->max('updated_at')),
                self::sitemapEntry(self::abs(route('sitemap.news', absolute: false)),
                    Post::query()->max('updated_at')),
            ];

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $entries)."\n"
                .'</sitemapindex>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Static + canonical app routes: /, /vehicles, /cif.
     */
    public function staticPages(): Response
    {
        $xml = Cache::remember('sitemap.static.xml', now()->addHour(), function (): string {
            $entries = [
                self::urlEntry(self::abs(route('home', absolute: false)), null, '1.0', 'daily'),
                self::urlEntry(self::abs(route('vehicles.index', absolute: false)), null, '0.9', 'hourly'),
                self::urlEntry(self::abs(route('cif.index', absolute: false)), null, '0.6', 'monthly'),
            ];

            return self::wrap($entries);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Every CMS page (Page model, status=published, not in future).
     */
    public function pages(): Response
    {
        $xml = Cache::remember('sitemap.pages.xml', now()->addHour(), function (): string {
            $entries = [];
            Page::query()
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->chunk(200, function ($pages) use (&$entries) {
                    foreach ($pages as $p) {
                        $entries[] = self::urlEntry(self::abs('/'.$p->slug), $p->updated_at, '0.7', 'weekly');
                    }
                });

            return self::wrap($entries);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Every published vehicle detail page. Chunked because catalogs grow.
     */
    public function vehicles(): Response
    {
        $xml = Cache::remember('sitemap.vehicles.xml', now()->addHour(), function (): string {
            $entries = [];
            Vehicle::query()
                ->published()
                ->select(['slug', 'updated_at'])
                ->chunk(500, function ($vehicles) use (&$entries) {
                    foreach ($vehicles as $v) {
                        $entries[] = self::urlEntry(self::abs(route('vehicles.show', $v->slug, absolute: false)), $v->updated_at, '0.6', 'weekly');
                    }
                });

            return self::wrap($entries);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Every published news post.
     */
    public function news(): Response
    {
        $xml = Cache::remember('sitemap.news.xml', now()->addHour(), function (): string {
            $entries = [];
            Post::query()
                ->published()
                ->select(['slug', 'updated_at'])
                ->chunk(500, function ($posts) use (&$entries) {
                    foreach ($posts as $p) {
                        $entries[] = self::urlEntry(self::abs(route('news.show', $p->slug, absolute: false)), $p->updated_at, '0.6', 'weekly');
                    }
                });

            return self::wrap($entries);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /favorites\nDisallow: /quotes\nDisallow: /profile\n\nSitemap: ".self::abs('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Force absolute URLs through the configured APP_URL host instead of
     * whatever PHP saw on the incoming request (which can be "localhost"
     * when LSAPI proxies forget to forward Host on cache:warm calls).
     */
    private static function abs(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#', $pathOrUrl)) {
            return $pathOrUrl;
        }
        $base = rtrim(config('app.url') ?: URL::to('/'), '/');

        return $base.'/'.ltrim($pathOrUrl, '/');
    }

    private static function wrap(array $entries): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $entries)."\n"
            .'</urlset>';
    }

    private static function urlEntry(string $loc, mixed $lastmod = null, string $priority = '0.5', string $changefreq = 'weekly'): string
    {
        $lm = $lastmod ? (is_string($lastmod) ? $lastmod : $lastmod->toIso8601String()) : null;

        return '  <url>'
            .'<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
            .($lm ? '<lastmod>'.htmlspecialchars($lm, ENT_XML1).'</lastmod>' : '')
            .'<changefreq>'.$changefreq.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .'</url>';
    }

    private static function sitemapEntry(string $loc, mixed $lastmod = null): string
    {
        $lm = $lastmod ? (is_string($lastmod) ? $lastmod : $lastmod->toIso8601String()) : null;

        return '  <sitemap>'
            .'<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
            .($lm ? '<lastmod>'.htmlspecialchars($lm, ENT_XML1).'</lastmod>' : '')
            .'</sitemap>';
    }
}
