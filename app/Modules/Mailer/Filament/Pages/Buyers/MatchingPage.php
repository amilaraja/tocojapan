<?php

namespace App\Modules\Mailer\Filament\Pages\Buyers;

use App\Models\Vehicle;
use App\Modules\Mailer\Domain\Buyers\BuyerExport;
use App\Modules\Mailer\Filament\Clusters\Buyers;
use App\Modules\Mailer\Filament\Resources\Buyers\BuyerResource;
use App\Modules\Mailer\Models\Buyer;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock matching (TOC-BUY-009): buyers who asked for a vehicle like this
 * one. Vehicles are only read (rule 3).
 */
class MatchingPage extends Page
{
    protected string $view = 'mailer::filament.matching';

    protected static ?string $cluster = Buyers::class;

    protected static ?string $slug = 'matching';

    protected static ?string $title = 'Buyers for a vehicle';

    protected static ?string $navigationLabel = 'Stock matching';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 3;

    public string $vehicleSearch = '';

    public string $make = '';

    public string $model = '';

    public ?int $yearFrom = null;

    public ?int $yearTo = null;

    public string $drive = '';

    public string $country = '';

    public string $months = '12';

    public bool $includeNoYear = true;

    public static function canAccess(): bool
    {
        return MailerAccess::canUse();
    }

    /** @return array<string, string> */
    public function countries(): array
    {
        return BuyerResource::countryOptions();
    }

    /** Published vehicles matching the search box (read-only). */
    /** @return Collection<int, Vehicle> */
    public function vehicleOptions(): Collection
    {
        $term = trim($this->vehicleSearch);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Vehicle::query()->published()
            ->with(['make:id,name', 'vehicleModel:id,name'])
            ->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('stock_no', 'like', "%{$term}%"))
            ->orderByDesc('published_at')->limit(8)
            ->get(['id', 'title', 'stock_no', 'make_id', 'vehicle_model_id', 'year_first_reg', 'steering_side']);
    }

    public function pickVehicle(int $id): void
    {
        $v = Vehicle::query()->with(['make:id,name', 'vehicleModel:id,name'])->find($id);
        if (! $v) {
            return;
        }
        $this->make = (string) $v->make?->name;
        $this->model = (string) $v->vehicleModel?->name;
        $this->yearFrom = $v->year_first_reg ? $v->year_first_reg - 2 : null;
        $this->yearTo = $v->year_first_reg ? $v->year_first_reg + 2 : null;
        $this->drive = $v->steering_side === 'left' ? 'LHD' : 'RHD';
        $this->vehicleSearch = '';
    }

    /** Buyers with at least one matching enquiry. */
    /** @return Builder<Buyer>|null */
    public function query(): ?Builder
    {
        if (trim($this->make) === '') {
            return null;
        }
        $norm = fn (string $col) => "LOWER(REPLACE(REPLACE(REPLACE({$col}, '-', ''), ' ', ''), '.', ''))";
        $key = fn (string $v) => mb_strtolower((string) preg_replace('/[\s.\-]+/u', '', $v));

        $matching = function ($e) use ($norm, $key) {
            $e->whereRaw($norm('make').' = ?', [$key($this->make)])
                ->when(trim($this->model) !== '', fn ($q) => $q->whereRaw($norm('model').' LIKE ?', ['%'.$key($this->model).'%']))
                ->when($this->yearFrom || $this->yearTo, function ($q) {
                    $q->where(function ($q) {
                        $q->where(function ($q) {
                            $q->when($this->yearFrom, fn ($q) => $q->where('year', '>=', $this->yearFrom))
                                ->when($this->yearTo, fn ($q) => $q->where('year', '<=', $this->yearTo));
                        });
                        if ($this->includeNoYear) {
                            $q->orWhereNull('year');
                        }
                    });
                })
                ->when($this->drive !== '', fn ($q) => $q->where(fn ($q) => $q->where('drive', $this->drive)->orWhere('drive', 'ANY')->orWhereNull('drive')))
                ->when($this->months !== 'all', fn ($q) => $q->where('received_at', '>=', now()->subMonths((int) $this->months)));
        };

        return Buyer::query()
            ->whereHas('enquiries', $matching)
            ->when($this->country !== '', fn ($q) => $q->where('country_code', $this->country))
            ->withCount(['enquiries as matching_count' => $matching])
            ->withMax(['enquiries as last_match_at' => $matching], 'received_at')
            ->orderByDesc('last_match_at');
    }

    /** @return Collection<int, Buyer> */
    public function results(): Collection
    {
        return $this->query()?->limit(200)->get() ?? collect();
    }

    public function total(): int
    {
        return $this->query()?->count() ?? 0;
    }

    public function download(): ?StreamedResponse
    {
        $q = $this->query();

        return $q ? BuyerExport::download(Buyer::query()->whereIn('id', $q->pluck('mailer_buyers.id')), 'toco-matching-buyers') : null;
    }

    /** @return list<string> */
    public function topMakes(): array
    {
        return DB::table('mailer_buyer_enquiries')->whereNotNull('make')->groupBy('make')->orderByRaw('COUNT(*) DESC')->limit(200)->pluck('make')->all();
    }
}
