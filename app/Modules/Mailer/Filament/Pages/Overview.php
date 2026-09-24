<?php

namespace App\Modules\Mailer\Filament\Pages;

use App\Modules\Mailer\Filament\Resources\Campaigns\CampaignResource;
use App\Modules\Mailer\Filament\Widgets\ImportStats;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** S2 Mailer Overview (TOC-GEN-006). Visible to Mailer Admins and Marketers. */
class Overview extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'mailer::filament.overview';

    protected static ?string $slug = 'mailer';

    protected static ?string $title = 'Mailer overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static string|\UnitEnum|null $navigationGroup = 'Mailer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return MailerAccess::canUse();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newCampaign')->label('New campaign')->icon('heroicon-o-plus')->url(CampaignResource::getUrl('create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [ImportStats::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest campaigns')
            ->query(Campaign::query()->withCount('vehicles')->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Campaign'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Campaign::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Campaign::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('vehicles_count')->label('Vehicles'),
                TextColumn::make('pushed_at')->label('Pushed')->dateTime('j M Y, H:i')->placeholder('Not yet'),
                TextColumn::make('updated_at')->label('Last edited')->since(),
            ])
            ->recordUrl(fn (Campaign $record) => CampaignResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('No campaigns yet')
            ->emptyStateDescription('Create your first campaign to pick vehicles and send a draft to Brevo.');
    }
}
