<?php

namespace App\Filament\Admin\Pages;

use App\Services\GoogleSearchConsoleService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SearchConsoleStats extends Page
{
    protected static ?string $navigationLabel = 'Search Console';

    protected static ?string $title = 'Google Search Console';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.admin.pages.search-console-stats';

    public bool $isConfigured = false;

    public ?array $summary = null;

    public ?array $topQueries = null;

    public ?array $topPages = null;

    public ?array $dailyData = null;

    public ?array $deviceData = null;

    public ?array $countryData = null;

    public ?array $indexCoverage = null;

    public ?array $contentBreakdown = null;

    public ?array $positionDistribution = null;

    public ?array $quickWins = null;

    public int $dateRange = 28;

    public ?string $siteUrl = null;

    public ?string $error = null;

    protected GoogleSearchConsoleService $gscService;

    public function boot(GoogleSearchConsoleService $gscService): void
    {
        $this->gscService = $gscService;
    }

    public function mount(): void
    {
        $this->isConfigured = $this->gscService->isConfigured();
        $this->siteUrl = $this->gscService->getSiteUrl();

        if ($this->isConfigured) {
            $this->loadData();
        }
    }

    public function updatedDateRange(): void
    {
        if ($this->isConfigured) {
            $this->loadData();
        }
    }

    public function loadData(): void
    {
        try {
            $this->summary = $this->gscService->getSummary($this->dateRange);
            $this->topQueries = $this->gscService->getTopQueries($this->dateRange, 100);
            $this->topPages = $this->gscService->getTopPages($this->dateRange, 100);
            $this->dailyData = $this->gscService->getDailyPerformance($this->dateRange);
            $this->deviceData = $this->gscService->getDeviceData($this->dateRange);
            $this->countryData = $this->gscService->getCountryData($this->dateRange);
            $this->indexCoverage = $this->gscService->getIndexCoverage();
            $this->buildInsights();
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    protected function buildInsights(): void
    {
        // Toco site structure: vehicles (browse + detail), CIF calculator,
        // homepage, and anything else (CMS pages, About/Contact, etc.).
        $sections = [
            'Vehicles' => ['prefix' => '/vehicles', 'clicks' => 0, 'impressions' => 0, 'pages' => 0, 'color' => '#1F2356'],
            'CIF & shipping' => ['prefix' => '/cif', 'clicks' => 0, 'impressions' => 0, 'pages' => 0, 'color' => '#10B981'],
            'Homepage' => ['prefix' => '__home__', 'clicks' => 0, 'impressions' => 0, 'pages' => 0, 'color' => '#E30613'],
            'CMS pages' => ['prefix' => '', 'clicks' => 0, 'impressions' => 0, 'pages' => 0, 'color' => '#6B7280'],
        ];

        foreach ($this->topPages ?? [] as $page) {
            $path = parse_url($page['key'], PHP_URL_PATH) ?? '/';
            if ($path === '/' || $path === '') {
                $bucket = 'Homepage';
            } elseif (str_starts_with($path, '/vehicles')) {
                $bucket = 'Vehicles';
            } elseif (str_starts_with($path, '/cif')) {
                $bucket = 'CIF & shipping';
            } else {
                $bucket = 'CMS pages';
            }
            $sections[$bucket]['clicks'] += $page['clicks'];
            $sections[$bucket]['impressions'] += $page['impressions'];
            $sections[$bucket]['pages']++;
        }

        $this->contentBreakdown = array_filter($sections, fn ($s) => $s['pages'] > 0);

        $dist = ['1-3' => 0, '4-10' => 0, '11-20' => 0, '21-50' => 0, '50+' => 0];
        foreach ($this->topQueries ?? [] as $q) {
            if ($q['position'] <= 3) {
                $dist['1-3']++;
            } elseif ($q['position'] <= 10) {
                $dist['4-10']++;
            } elseif ($q['position'] <= 20) {
                $dist['11-20']++;
            } elseif ($q['position'] <= 50) {
                $dist['21-50']++;
            } else {
                $dist['50+']++;
            }
        }
        $this->positionDistribution = $dist;

        // Quick wins: position 4-20 with at least 20 impressions
        $wins = array_filter($this->topQueries ?? [], function ($q) {
            return $q['position'] >= 4 && $q['position'] <= 20 && $q['impressions'] >= 20;
        });
        usort($wins, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
        $this->quickWins = array_slice($wins, 0, 10);
    }

    public function refreshData(): void
    {
        $this->gscService->clearCache();
        $this->loadData();

        Notification::make()
            ->title('Data refreshed from Google Search Console')
            ->success()
            ->send();
    }

    public function formatDevice(string $device): string
    {
        return match (strtolower($device)) {
            'mobile' => 'Mobile',
            'desktop' => 'Desktop',
            'tablet' => 'Tablet',
            default => ucfirst($device),
        };
    }

    public function getCountryFlag(string $countryCode): string
    {
        $code = strtoupper($countryCode);
        if (strlen($code) !== 3) {
            return '';
        }

        $map = [
            'usa' => 'us', 'gbr' => 'gb', 'aus' => 'au', 'nzl' => 'nz', 'ind' => 'in',
            'can' => 'ca', 'deu' => 'de', 'fra' => 'fr', 'jpn' => 'jp', 'chn' => 'cn',
            'lka' => 'lk', 'sgp' => 'sg', 'mys' => 'my', 'phl' => 'ph', 'idn' => 'id',
            'tha' => 'th', 'vnm' => 'vn', 'kor' => 'kr', 'are' => 'ae', 'sau' => 'sa',
            'zaf' => 'za', 'bra' => 'br', 'mex' => 'mx', 'arg' => 'ar', 'ita' => 'it',
            'esp' => 'es', 'nld' => 'nl', 'bel' => 'be', 'che' => 'ch', 'aut' => 'at',
            'pol' => 'pl', 'swe' => 'se', 'nor' => 'no', 'dnk' => 'dk', 'fin' => 'fi',
            'irl' => 'ie', 'prt' => 'pt', 'grc' => 'gr', 'tur' => 'tr', 'rus' => 'ru',
            'ukr' => 'ua', 'pak' => 'pk', 'bgd' => 'bd', 'npl' => 'np', 'mmr' => 'mm',
            'ken' => 'ke', 'tza' => 'tz', 'uga' => 'ug', 'zwe' => 'zw', 'cod' => 'cd',
            'cog' => 'cg', 'cmr' => 'cm', 'gha' => 'gh', 'nga' => 'ng',
        ];

        $twoLetter = $map[strtolower($code)] ?? substr($code, 0, 2);

        $flag = '';
        foreach (str_split(strtoupper($twoLetter)) as $char) {
            $flag .= mb_chr(ord($char) - ord('A') + 0x1F1E6);
        }

        return $flag;
    }
}
