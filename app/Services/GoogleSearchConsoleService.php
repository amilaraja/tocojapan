<?php

namespace App\Services;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\Webmasters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleService
{
    protected ?Client $client = null;
    protected ?SearchConsole $searchConsole = null;
    protected ?string $siteUrl = null;

    protected int $cacheDuration = 3600;

    public function __construct()
    {
        $this->siteUrl = config('services.google.search_console.site_url');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->siteUrl) && file_exists($this->credentialsPath());
    }

    protected function credentialsPath(): string
    {
        $rel = config('services.google.search_console.credentials_path', 'app/google-credentials.json');

        return storage_path($rel);
    }

    protected function getClient(): ?Client
    {
        if ($this->client) {
            return $this->client;
        }
        if (! $this->isConfigured()) {
            return null;
        }
        try {
            $this->client = new Client();
            $this->client->setApplicationName(config('app.name').' GSC Integration');
            $this->client->setScopes([SearchConsole::WEBMASTERS_READONLY]);
            $this->client->setAuthConfig($this->credentialsPath());

            return $this->client;
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google Client', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function getSearchConsole(): ?SearchConsole
    {
        if ($this->searchConsole) {
            return $this->searchConsole;
        }
        $client = $this->getClient();
        if (! $client) {
            return null;
        }
        try {
            $this->searchConsole = new SearchConsole($client);

            return $this->searchConsole;
        } catch (\Exception $e) {
            Log::error('Failed to initialize Search Console service', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getSearchAnalytics(
        string $startDate,
        string $endDate,
        string $dimension = 'query',
        int $rowLimit = 25
    ): ?array {
        $cacheKey = "gsc_analytics_{$dimension}_{$startDate}_{$endDate}_{$rowLimit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $searchConsole = $this->getSearchConsole();
        if (! $searchConsole) {
            return null;
        }

        try {
            $request = new SearchConsole\SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setDimensions([$dimension]);
            $request->setRowLimit($rowLimit);

            $response = $searchConsole->searchanalytics->query($this->siteUrl, $request);

            $rows = [];
            foreach ($response->getRows() ?? [] as $row) {
                $rows[] = [
                    'key' => $row->getKeys()[0] ?? '',
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => round($row->getCtr() * 100, 2),
                    'position' => round($row->getPosition(), 1),
                ];
            }

            Cache::put($cacheKey, $rows, $this->cacheDuration);

            return $rows;
        } catch (\Exception $e) {
            Log::error('GSC Search Analytics error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getTopQueries(int $days = 28, int $limit = 25): ?array
    {
        return $this->getSearchAnalytics(
            date('Y-m-d', strtotime("-{$days} days")),
            date('Y-m-d'),
            'query',
            $limit
        );
    }

    public function getTopPages(int $days = 28, int $limit = 25): ?array
    {
        return $this->getSearchAnalytics(
            date('Y-m-d', strtotime("-{$days} days")),
            date('Y-m-d'),
            'page',
            $limit
        );
    }

    public function getCountryData(int $days = 28, int $limit = 10): ?array
    {
        return $this->getSearchAnalytics(
            date('Y-m-d', strtotime("-{$days} days")),
            date('Y-m-d'),
            'country',
            $limit
        );
    }

    public function getDeviceData(int $days = 28): ?array
    {
        return $this->getSearchAnalytics(
            date('Y-m-d', strtotime("-{$days} days")),
            date('Y-m-d'),
            'device',
            10
        );
    }

    public function getDailyPerformance(int $days = 28): ?array
    {
        $cacheKey = "gsc_daily_performance_{$days}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $searchConsole = $this->getSearchConsole();
        if (! $searchConsole) {
            return null;
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $request = new SearchConsole\SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setDimensions(['date']);
            $request->setRowLimit(100);

            $response = $searchConsole->searchanalytics->query($this->siteUrl, $request);

            $rows = [];
            foreach ($response->getRows() ?? [] as $row) {
                $rows[] = [
                    'date' => $row->getKeys()[0] ?? '',
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => round($row->getCtr() * 100, 2),
                    'position' => round($row->getPosition(), 1),
                ];
            }

            usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']));

            Cache::put($cacheKey, $rows, $this->cacheDuration);

            return $rows;
        } catch (\Exception $e) {
            Log::error('GSC Daily Performance error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getSummary(int $days = 28): ?array
    {
        $cacheKey = "gsc_summary_{$days}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $searchConsole = $this->getSearchConsole();
        if (! $searchConsole) {
            return null;
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $request = new SearchConsole\SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);

            $response = $searchConsole->searchanalytics->query($this->siteUrl, $request);

            $rows = $response->getRows() ?? [];
            $result = ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];
            if (! empty($rows)) {
                $row = $rows[0];
                $result = [
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => round($row->getCtr() * 100, 2),
                    'position' => round($row->getPosition(), 1),
                ];
            }

            Cache::put($cacheKey, $result, $this->cacheDuration);

            return $result;
        } catch (\Exception $e) {
            Log::error('GSC Summary error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getIndexCoverage(): ?array
    {
        $cacheKey = 'gsc_index_coverage';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $client = $this->getClient();
        if (! $client) {
            return null;
        }

        try {
            $webmasters = new Webmasters($client);
            $sitemaps = $webmasters->sitemaps->listSitemaps($this->siteUrl);

            $totalSubmittedFromSitemap = 0;
            $totalIndexedFromSitemap = 0;
            $sitemapData = [];

            foreach ($sitemaps->getSitemap() ?? [] as $sitemap) {
                $contents = $sitemap->getContents();
                $submitted = 0;
                $indexed = 0;

                if ($contents && count($contents) > 0) {
                    foreach ($contents as $content) {
                        $submitted += $content->getSubmitted() ?? 0;
                        $indexed += $content->getIndexed() ?? 0;
                    }
                }

                $sitemapData[] = [
                    'path' => $sitemap->getPath(),
                    'submitted' => $submitted,
                    'indexed' => $indexed,
                    'last_submitted' => $sitemap->getLastSubmitted(),
                    'last_downloaded' => $sitemap->getLastDownloaded(),
                ];

                $totalSubmittedFromSitemap += $submitted;
                $totalIndexedFromSitemap += $indexed;
            }

            // Sitemaps API has been returning 0 for indexed since late 2024;
            // estimate via pages that have impressions in the last 90 days.
            $totalIndexed = $totalIndexedFromSitemap;
            if ($totalIndexed === 0 && $totalSubmittedFromSitemap > 0) {
                try {
                    $searchAnalytics = $webmasters->searchanalytics;
                    $request = new \Google\Service\Webmasters\SearchAnalyticsQueryRequest();
                    $request->setStartDate(date('Y-m-d', strtotime('-90 days')));
                    $request->setEndDate(date('Y-m-d', strtotime('-2 days')));
                    $request->setDimensions(['page']);
                    $request->setRowLimit(25000);

                    $response = $searchAnalytics->query($this->siteUrl, $request);
                    $rows = $response->getRows() ?? [];

                    $totalIndexed = count($rows);

                    if (! empty($sitemapData)) {
                        $sitemapData[0]['indexed'] = $totalIndexed;
                    }
                } catch (\Exception $e) {
                    Log::warning('GSC: Could not estimate indexed pages: '.$e->getMessage());
                }
            }

            $result = [
                'total_indexed' => $totalIndexed,
                'sitemaps' => $sitemapData,
            ];

            Cache::put($cacheKey, $result, $this->cacheDuration);

            return $result;
        } catch (\Exception $e) {
            Log::error('GSC Index Coverage error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function clearCache(): void
    {
        foreach ([7, 14, 28, 90] as $days) {
            Cache::forget("gsc_daily_performance_{$days}");
            Cache::forget("gsc_summary_{$days}");

            foreach (['query', 'page', 'country', 'device'] as $dim) {
                $endDate = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime("-{$days} days"));
                foreach ([10, 25, 100] as $limit) {
                    Cache::forget("gsc_analytics_{$dim}_{$startDate}_{$endDate}_{$limit}");
                }
            }
        }

        Cache::forget('gsc_index_coverage');
    }

    public function getSiteUrl(): ?string
    {
        return $this->siteUrl;
    }
}
