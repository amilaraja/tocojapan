<?php

namespace App\Filament\Admin\Widgets;

use App\Services\GoogleSearchConsoleService;
use Filament\Widgets\ChartWidget;

class SearchConsoleChart extends ChartWidget
{
    protected ?string $heading = 'Search Console — last 28 days';

    protected ?string $description = 'Daily clicks (red) + impressions (navy). Data is delayed 2–3 days by Google.';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '28';

    protected function getFilters(): ?array
    {
        return [
            '7' => '7 days',
            '14' => '14 days',
            '28' => '28 days',
            '90' => '3 months',
        ];
    }

    protected function getData(): array
    {
        $service = app(GoogleSearchConsoleService::class);
        if (! $service->isConfigured()) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        $days = (int) ($this->filter ?? 28);
        $rows = $service->getDailyPerformance($days) ?? [];

        $labels = array_map(fn ($r) => date('M d', strtotime($r['date'])), $rows);
        $clicks = array_map(fn ($r) => (int) $r['clicks'], $rows);
        $impressions = array_map(fn ($r) => (int) $r['impressions'], $rows);

        return [
            'datasets' => [
                [
                    'label' => 'Clicks',
                    'data' => $clicks,
                    'backgroundColor' => '#E30613',
                    'borderColor' => '#E30613',
                    'borderWidth' => 0,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Impressions',
                    'data' => $impressions,
                    'backgroundColor' => '#1F2356',
                    'borderColor' => '#1F2356',
                    'borderWidth' => 0,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'top']],
            'scales' => [
                'y' => ['position' => 'left', 'title' => ['display' => true, 'text' => 'Clicks']],
                'y1' => ['position' => 'right', 'title' => ['display' => true, 'text' => 'Impressions'], 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }

    public static function canView(): bool
    {
        return app(GoogleSearchConsoleService::class)->isConfigured();
    }
}
