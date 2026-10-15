<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\Reports as Numbers;
use App\Modules\Crm\Filament\Concerns\HasPeriod;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Funnel, lost reasons, sources, time in stage, response times, won value (CRM-DSH-005). */
class Reports extends Page
{
    use HasPeriod;

    protected string $view = 'crm::filament.reports';

    protected static ?string $slug = 'reports';

    protected static ?string $title = 'Sales reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return CrmAccess::isAdmin() || CrmAccess::isTeamLead();
    }

    /** @return array<string, array{title: string, head: list<string>, rows: list<list<mixed>>}> */
    public function tables(): array
    {
        [$from, $to] = [$this->periodFrom(), $this->periodTo()];
        $funnel = Numbers::funnel($from, $to);
        $top = max(1, $funnel[1] ?? 0);

        return [
            'funnel' => ['title' => 'Funnel: vehicle-sale Cases created in the period', 'head' => ['Stage', 'Cases', '% of stage 01'],
                'rows' => collect($funnel)->map(fn ($c, $s) => [$s === 6 ? 'Won' : Crm::stageLabel($s), $c, round($c * 100 / $top).' %'])->values()->all()],
            'lost' => ['title' => 'Lost sales by reason', 'head' => ['Reason', 'Cases', 'Value (USD)'],
                'rows' => Numbers::lostReasons($from, $to)->map(fn ($r) => [$r->reason ?: '—', (int) $r->c, number_format((float) $r->v)])->all()],
            'sources' => ['title' => 'Sources', 'head' => ['Source', 'Cases', 'Won', 'Conversion', 'Won value (USD)'],
                'rows' => Numbers::sources($from, $to)->map(fn ($r) => [$r['source'], $r['cases'], $r['won'], $r['conversion'].' %', number_format($r['value'])])->all()],
            'stages' => ['title' => 'Average time in each stage (days)', 'head' => ['Stage', 'Days'],
                'rows' => collect(Numbers::timeInStage($from, $to))->map(fn ($d, $s) => [Crm::stageLabel($s), $d ?? '—'])->values()->all()],
            'response' => ['title' => 'Response times', 'head' => ['Measure', 'Clocks', 'Average', 'On time', 'Breached'],
                'rows' => Numbers::responseTimes($from, $to)->map(fn ($r) => [$r['measure'], $r['count'], $r['avg_minutes'] === null ? '—' : ($r['avg_minutes'] >= 1440 ? round($r['avg_minutes'] / 1440, 1).' days' : ($r['avg_minutes'] >= 60 ? round($r['avg_minutes'] / 60, 1).' h' : $r['avg_minutes'].' min')), $r['on_time'] === null ? '—' : $r['on_time'].' %', $r['breached']])->all()],
            'month' => ['title' => 'Cars sold by month', 'head' => ['Month', 'Cars', 'Value (USD)'],
                'rows' => Numbers::wonByMonth($from, $to)->map(fn ($r) => [$r->month, $r->c, number_format($r->v)])->all()],
            'country' => ['title' => 'Cars sold by country', 'head' => ['Country', 'Cars', 'Value (USD)'],
                'rows' => Numbers::wonByCountry($from, $to)->map(fn ($r) => [$r->country, (int) $r->c, number_format((float) $r->v)])->all()],
        ];
    }

    /** CSV that opens in Excel (UTF-8 with BOM). */
    public function export(string $key): StreamedResponse
    {
        $table = $this->tables()[$key] ?? abort(404);
        $name = 'toco-crm-'.$key.'-'.$this->from.'-to-'.$this->until.'.csv';

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $table['head']);
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) ? str_replace(',', '', $v) : $v, $row));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
