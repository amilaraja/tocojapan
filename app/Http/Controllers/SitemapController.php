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
            ];
            // Only advertise the news sitemap once there's at least one published
            // post — Search Console rejects an empty <urlset> as "missing url tag".
            $newsLastmod = Post::query()->published()->max('updated_at');
            if ($newsLastmod) {
                $entries[] = self::sitemapEntry(self::abs(route('sitemap.news', absolute: false)), $newsLastmod);
            }

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
        // 404 when there are no published posts so the URL doesn't ship an
        // empty <urlset> that Search Console flags as schema-invalid.
        if (! Post::query()->published()->exists()) {
            abort(404);
        }

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

    /**
     * Normalise any timestamp (Carbon, DateTime, "YYYY-MM-DD HH:MM:SS" string,
     * null) into an ISO-8601 sitemap-friendly string. Returns null when the
     * input is null/empty so the helper can skip the <lastmod> tag.
     *
     * `Model::max('updated_at')` returns a raw MySQL string, not a Carbon —
     * the previous "is_string ? leave alone" branch let invalid dates straight
     * through to Search Console, which then flagged them.
     */
    private static function iso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value)->toIso8601String();
        }
        try {
            return \Carbon\Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function urlEntry(string $loc, mixed $lastmod = null, string $priority = '0.5', string $changefreq = 'weekly'): string
    {
        $lm = self::iso8601($lastmod);

        return '  <url>'
            .'<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
            .($lm ? '<lastmod>'.htmlspecialchars($lm, ENT_XML1).'</lastmod>' : '')
            .'<changefreq>'.$changefreq.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .'</url>';
    }

    private static function sitemapEntry(string $loc, mixed $lastmod = null): string
    {
        $lm = self::iso8601($lastmod);

        return '  <sitemap>'
            .'<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
            .($lm ? '<lastmod>'.htmlspecialchars($lm, ENT_XML1).'</lastmod>' : '')
            .'</sitemap>';
    }
}
