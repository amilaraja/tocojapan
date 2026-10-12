<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Models\Country;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmUi;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/** Five columns, one card per open vehicle sale (CRM-DSH-002). */
class PipelineBoard extends Page
{
    protected string $view = 'crm::filament.pipeline';

    protected static ?string $slug = 'crm/pipeline';

    protected static ?string $title = 'Pipeline';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 5;

    #[Url] public ?string $owner = null;

    #[Url] public ?string $source = null;

    #[Url] public ?string $country = null;

    #[Url] public ?string $dealer = null;

    #[Url] public ?string $from = null;

    #[Url] public ?string $until = null;

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    /** @return Collection<int, Collection<int, CrmCase>> */
    public function columns(): Collection
    {
        $cases = CrmAccess::scopeCases(CrmCase::query()->active()->where('type', Crm::TYPE_VEHICLE))
            ->with(['customer', 'vehicle', 'owner', 'slaTimers' => fn ($q) => $q->whereNull('met_at')->whereNull('cancelled_at')])
            ->when($this->owner === 'none', fn ($q) => $q->whereNull('owner_id'))
            ->when($this->owner && $this->owner !== 'none', fn ($q) => $q->where('owner_id', $this->owner))
            ->when($this->source, fn ($q) => $q->where('source', $this->source))
            ->when($this->country, fn ($q) => $q->where('dest_country_id', $this->country))
            ->when($this->dealer === 'yes', fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->partnerStock()))
            ->when($this->dealer === 'no', fn ($q) => $q->whereDoesntHave('vehicle', fn ($v) => $v->partnerStock()))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->until, fn ($q) => $q->whereDate('created_at', '<=', $this->until))
            ->orderBy('stage_entered_at')
            ->limit(500)->get();

        return collect(array_keys(Crm::STAGES))->mapWithKeys(fn ($stage) => [$stage => $cases->where('stage', $stage)->values()]);
    }

    /** @return array<string, array<string, string>> */
    public function filterOptions(): array
    {
        return [
            'owner' => ['' => 'Everyone', 'none' => 'Unassigned'] + CrmUi::staffOptions(),
            'source' => ['' => 'All sources'] + Crm::SOURCES,
            'country' => ['' => 'All countries'] + Country::whereIn('id', CrmCase::query()->whereNotNull('dest_country_id')->distinct()->pluck('dest_country_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'dealer' => ['' => 'Own and dealer stock', 'no' => 'Own stock only', 'yes' => 'Dealer stock only'],
        ];
    }
}
