<?php

namespace App\Modules\PartnerApi\Filament\Pages;

use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Usage\Quota;
use App\Modules\PartnerApi\Domain\Usage\UsageReport;
use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerRequestLog;
use App\Modules\PartnerApi\Models\PartnerWebhookDelivery;
use App\Modules\PartnerApi\Support\PartnerAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

/**
 * Admin → Partner Network → Usage (UI brief section 4): a window on one
 * partner's traffic, not a control panel. No actions here.
 */
class PartnerUsage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'partner-api::usage';

    protected static ?string $slug = 'partner-usage';

    protected static ?string $title = 'Partner usage';

    protected static ?string $navigationLabel = 'Usage';

    protected static string|\UnitEnum|null $navigationGroup = 'Partner Network';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 3;

    /** Partner shown; ?partner=ID from the Partners table "View usage" link. */
    #[Url]
    public ?int $partner = null;

    public static function canAccess(): bool
    {
        return PartnerAccess::canAdmin();
    }

    public function mount(): void
    {
        $this->partner ??= Partner::query()->where('status', '!=', Partner::STATUS_REVOKED)
            ->orderByDesc('last_activity_at')->value('id');
    }

    public function updatedPartner(): void
    {
        $this->resetTable();
    }

    public function selected(): ?Partner
    {
        return $this->partner ? Partner::find($this->partner) : null;
    }

    /** @return array<int, string> */
    public function partnerOptions(): array
    {
        return Partner::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        $partner = $this->selected();
        if (! $partner) {
            return [];
        }

        $calls = Quota::usedToday($partner, 'api');
        $photos = Quota::usedToday($partner, 'image');
        $report = UsageReport::for($partner);

        return [
            'calls' => $calls,
            'calls_limit' => $partner->rate_per_day,
            'calls_pct' => $partner->rate_per_day > 0 ? min(100, (int) round($calls / $partner->rate_per_day * 100)) : 0,
            'photos' => $photos,
            'photos_limit' => $partner->image_daily_limit,
            'photos_pct' => $partner->image_daily_limit > 0 ? min(100, (int) round($photos / $partner->image_daily_limit * 100)) : 0,
            'errors' => $report->errorsToday(),
            'holds' => $partner->isSelling() ? VehicleHold::active()->where('partner_id', $partner->id)->count() : null,
            'max_holds' => $partner->max_active_holds,
            'last_sync' => $report->lastSyncAt(),
            'days' => $report->days(30),
        ];
    }

    /** @return array<string, mixed>|null */
    public function webhookHealth(): ?array
    {
        $partner = $this->selected();
        if (! $partner || ! $partner->webhook_url) {
            return null;
        }

        $failing = PartnerWebhookDelivery::where('partner_id', $partner->id)
            ->where('status', PartnerWebhookDelivery::PENDING)->where('attempts', '>', 0);
        $oldest = (clone $failing)->min('created_at');
        $last = PartnerWebhookDelivery::where('partner_id', $partner->id)->latest('updated_at')->first();

        return [
            'last_delivered' => PartnerWebhookDelivery::where('partner_id', $partner->id)->max('delivered_at'),
            'failing' => (clone $failing)->count(),
            'gave_up' => PartnerWebhookDelivery::where('partner_id', $partner->id)->where('status', PartnerWebhookDelivery::FAILED)
                ->where('created_at', '>=', now()->subDays(7))->count(),
            'failing_over_hour' => $oldest !== null && \Illuminate\Support\Carbon::parse($oldest)->lt(now()->subHour()),
            'last_error' => $last?->last_error,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent activity')
            ->query(PartnerRequestLog::query()->where('partner_id', $this->partner ?? 0)->latest('id')->limit(50))
            ->paginated(false)
            ->emptyStateHeading('No activity yet')
            ->columns([
                TextColumn::make('created_at')->label('Time')->since()
                    ->description(fn (PartnerRequestLog $record) => $record->created_at->copy()->timezone('Asia/Tokyo')->format('j M H:i:s')),
                TextColumn::make('endpoint')->label('What')->formatStateUsing(fn (string $state) => self::describe($state)),
                TextColumn::make('status')->label('Result')->badge()
                    ->formatStateUsing(fn (int $state) => match (true) {
                        $state < 300 => 'OK',
                        $state === 304 => 'No change',
                        $state === 429 => 'Over limit',
                        $state === 404 => 'Not found',
                        $state < 500 => 'Refused ('.$state.')',
                        default => 'Error ('.$state.')',
                    })
                    ->color(fn (int $state) => match (true) {
                        $state < 400 => 'success',
                        $state < 500 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('duration_ms')->label('Took')->suffix(' ms'),
            ]);
    }

    public static function describe(string $endpoint): string
    {
        return [
            'partner.token' => 'Signed in',
            'partner.me' => 'Checked its keys',
            'partner.vehicles.index' => 'Synced stock',
            'partner.vehicles.show' => 'Read one vehicle',
            'partner.image' => 'Downloaded a photo',
            'partner.usage' => 'Read its usage',
            'partner.cif' => 'Asked for a CIF estimate',
            'partner.holds.store' => 'Placed a hold',
            'partner.holds.index' => 'Listed its holds',
            'partner.holds.destroy' => 'Released a hold',
        ][$endpoint] ?? (str_starts_with($endpoint, 'partner.catalog.') ? 'Read a reference list' : $endpoint);
    }
}
