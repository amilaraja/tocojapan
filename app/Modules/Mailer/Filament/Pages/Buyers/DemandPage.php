<?php

namespace App\Modules\Mailer\Filament\Pages\Buyers;

use App\Modules\Mailer\Filament\Clusters\Buyers;
use App\Modules\Mailer\Filament\Resources\Buyers\BuyerResource;
use App\Modules\Mailer\Models\BuyerEnquiry;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Demand report: what buyers ask for, where, and when (TOC-BUY-008). */
class DemandPage extends Page
{
    protected string $view = 'mailer::filament.demand';

    protected static ?string $cluster = Buyers::class;

    protected static ?string $slug = 'demand';

    protected static ?string $title = 'Buyer demand';

    protected static ?string $navigationLabel = 'Demand';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 2;

    public string $days = '90';

    public string $country = '';

    public static function canAccess(): bool
    {
        return MailerAccess::canUse();
    }

    /** @return array<string, string> */
    public function countries(): array
    {
        return BuyerResource::countryOptions();
    }

    protected function base(): Builder
    {
        return DB::table('mailer_buyer_enquiries as e')
            ->when($this->days !== 'all', fn (Builder $q) => $q->where('e.received_at', '>=', now()->subDays((int) $this->days)))
            ->when($this->country !== '', fn (Builder $q) => $q->where('e.country_code', $this->country));
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        $top = fn (string $select, string $group, int $limit) => $this->base()
            ->selectRaw("{$select}, COUNT(*) as n, COUNT(DISTINCT e.buyer_id) as buyers")
            ->groupByRaw($group)->orderByDesc('n')->limit($limit)->get();

        return [
            'total' => $this->base()->count(),
            'buyers' => $this->base()->distinct()->count('e.buyer_id'),
            'makes' => $top('e.make as label', 'e.make', 15)->where('label', '!=', null)->values(),
            'models' => $top($this->makeModelSql().' as label', 'e.make, e.model', 25)->where('label', '!=', '')->values(),
            'countries' => $this->base()->leftJoin('mailer_buyers as b', 'b.id', '=', 'e.buyer_id')
                ->selectRaw('COALESCE(MAX(b.country), e.country_code) as label, COUNT(*) as n, COUNT(DISTINCT e.buyer_id) as buyers')
                ->whereNotNull('e.country_code')->groupBy('e.country_code')->orderByDesc('n')->limit(15)->get(),
            'kinds' => $top('e.kind as label', 'e.kind', 5)->map(fn ($r) => (object) ['label' => BuyerEnquiry::KINDS[$r->label] ?? $r->label, 'n' => $r->n, 'buyers' => $r->buyers]),
            'drive' => $top("COALESCE(e.drive, 'Not given') as label", 'e.drive', 4),
            'months' => $this->base()->selectRaw($this->monthSql().' as label, COUNT(*) as n, COUNT(DISTINCT e.buyer_id) as buyers')
                ->groupByRaw($this->monthSql())->orderBy('label')->get(),
            'types' => $this->base()->join('mailer_buyers as b', 'b.id', '=', 'e.buyer_id')
                ->selectRaw("COALESCE(b.buyer_type, 'unknown') as label, COUNT(*) as n, COUNT(DISTINCT e.buyer_id) as buyers")
                ->groupBy('b.buyer_type')->orderByDesc('n')->get()
                ->map(fn ($r) => (object) ['label' => ['individual' => 'Individual', 'dealer' => 'Dealer / importer'][$r->label] ?? 'Not given', 'n' => $r->n, 'buyers' => $r->buyers]),
        ];
    }

    protected function makeModelSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(COALESCE(e.make,'') || ' ' || COALESCE(e.model,''))"
            : "TRIM(CONCAT(COALESCE(e.make,''), ' ', COALESCE(e.model,'')))";
    }

    protected function monthSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', e.received_at)"
            : "DATE_FORMAT(e.received_at, '%Y-%m')";
    }
}
