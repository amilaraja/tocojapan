<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Vehicle;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function (): string {
            $entries = [];

            // Static pages.
            $entries[] = self::entry(url('/'), priority: '1.0', changefreq: 'daily');
            $entries[] = self::entry(route('vehicles.index'), priority: '0.9', changefreq: 'hourly');
            $entries[] = self::entry(route('cif.index'), priority: '0.6', changefreq: 'monthly');

            // Published pages (CMS).
            Page::query()
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->chunk(200, function ($pages) use (&$entries) {
                    foreach ($pages as $p) {
                        $entries[] = self::entry(url('/'.$p->slug), $p->updated_at, '0.7', 'weekly');
                    }
                });

            // Published vehicle detail pages.
            Vehicle::query()
                ->published()
                ->select(['slug', 'updated_at'])
                ->chunk(500, function ($vehicles) use (&$entries) {
                    foreach ($vehicles as $v) {
                        $entries[] = self::entry(route('vehicles.show', $v->slug), $v->updated_at, '0.6', 'weekly');
                    }
                });

            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $entries)."\n"
                .'</urlset>';

            return $body;
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /favorites\nDisallow: /quotes\nDisallow: /profile\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    private static function entry(string $loc, mixed $lastmod = null, string $priority = '0.5', string $changefreq = 'weekly'): string
    {
        $lm = $lastmod ? (is_string($lastmod) ? $lastmod : $lastmod->toIso8601String()) : null;

        return '  <url>'
            .'<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
            .($lm ? '<lastmod>'.htmlspecialchars($lm, ENT_XML1).'</lastmod>' : '')
            .'<changefreq>'.$changefreq.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .'</url>';
    }
}
