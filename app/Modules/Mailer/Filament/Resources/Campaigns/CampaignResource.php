<?php

namespace App\Modules\Mailer\Filament\Resources\Campaigns;

use App\Modules\Mailer\Domain\Brevo\BrevoDirectory;
use App\Modules\Mailer\Domain\Campaigns\CampaignDuplicator;
use App\Modules\Mailer\Models\Banner;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Support\MailerAccess;
use App\Modules\Mailer\Support\MailerSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** S3 Campaign list and S4 Campaign Builder (TOC-CB-001 to 008). Marketers and Admins. */
class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $slug = 'mailer/campaigns';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?string $modelLabel = 'campaign';

    protected static string|\UnitEnum|null $navigationGroup = 'Mailer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return MailerAccess::canUse();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            View::make('mailer::filament.campaign-notices')->visibleOn('edit')->columnSpanFull(),

            Section::make('Campaign details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Internal name')->helperText('Only staff see this.')->required()->maxLength(150),
                    TextInput::make('subject')->label('Subject line')->maxLength(120),
                    TextInput::make('preview_text')->label('Preview text')->helperText('Shown after the subject in most inboxes.')->maxLength(140),
                    TextInput::make('kicker')->label('Small red heading')->placeholder('THIS WEEK’S PICKS')->maxLength(80),
                    TextInput::make('headline')->label('Headline')->maxLength(150)->columnSpanFull(),
                    Textarea::make('intro')->label('Intro paragraph')->rows(3)->maxLength(600)->columnSpanFull(),
                    Select::make('sender_id')
                        ->label('From')
                        ->options(fn () => app(BrevoDirectory::class)->senders())
                        ->helperText(fn () => app(BrevoDirectory::class)->senders() === [] ? 'No senders loaded. Brevo must be connected in Mailer settings.' : null)
                        ->hintAction(static::refreshBrevoAction()),
                    Select::make('list_ids')
                        ->label('Send to these Brevo lists')
                        ->multiple()
                        ->options(fn () => app(BrevoDirectory::class)->lists()),
                    TextInput::make('cta_url')
                        ->label('"Send a request" button link')
                        ->url()
                        ->placeholder(fn () => app(MailerSettings::class)->get('cta_url'))
                        ->helperText('Leave empty to use the default from Mailer settings.')
                        ->columnSpanFull(),
                ]),

            Section::make('Banner')
                ->schema([
                    Select::make('banner_id')
                        ->label('Banner')
                        ->placeholder('No banner')
                        // Archived banners cannot be picked, but stay on campaigns that use them (TOC-BAN-003).
                        ->options(fn (?Campaign $record) => Banner::query()
                            ->where(fn ($q) => $q->whereNull('archived_at')->when($record?->banner_id, fn ($q) => $q->orWhere('id', $record->banner_id)))
                            ->latest()->pluck('name', 'id'))
                        ->helperText('Upload new banners under Mailer, Banners.'),
                ]),

            Section::make('Vehicles')
                ->columnSpanFull()
                ->description('2 to 12 vehicles. Press "Add vehicles" at the top to find stock. Drag to reorder, or use the arrows.')
                ->visibleOn('edit')
                ->schema([
                    Repeater::make('vehicles')
                        ->label('')
                        ->schema([
                            ViewField::make('id')
                                ->view('mailer::filament.vehicle-row')
                                ->viewData(fn ($livewire) => ['snapshots' => $livewire->vehicleSnapshots ?? [], 'issues' => $livewire->issues ?? []]),
                        ])
                        ->addable(false)
                        ->reorderableWithDragAndDrop()
                        ->reorderableWithButtons()
                        ->deletable()
                        ->maxItems((int) config('mailer.campaign.max_vehicles', 12))
                        ->itemLabel(fn (array $state, $livewire) => ($livewire->vehicleSnapshots[$state['id'] ?? 0]['stockRef'] ?? ''))
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('vehicles'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Campaign')->searchable()->description(fn (Campaign $r) => $r->subject),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Campaign::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => Campaign::STATUS_COLORS[$state] ?? 'gray')
                    ->icon(fn (string $state) => [
                        'draft' => Heroicon::OutlinedPencil, 'in_brevo' => Heroicon::OutlinedPaperAirplane,
                        'changed' => Heroicon::OutlinedExclamationTriangle, 'sent' => Heroicon::OutlinedCheckCircle,
                        'archived' => Heroicon::OutlinedArchiveBox,
                    ][$state] ?? null),
                TextColumn::make('vehicles_count')->label('Vehicles'),
                TextColumn::make('list_ids')->label('Lists')
                    ->formatStateUsing(fn ($state) => app(BrevoDirectory::class)->lists()[(int) $state] ?? '#'.$state)
                    ->badge()->color('gray'),
                TextColumn::make('pushed_at')->label('Pushed')->dateTime('j M Y')->placeholder('—')->sortable(),
                TextColumn::make('sent_at')->label('Sent')->dateTime('j M Y')->placeholder('—')->sortable(),
                TextColumn::make('stats.opens')->label('Opens')->placeholder('—')->numeric(),
                TextColumn::make('stats.clicks')->label('Clicks')->placeholder('—')->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Campaign::STATUS_LABELS),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
                static::duplicateAction(),
            ])
            ->emptyStateHeading('No campaigns yet')
            ->emptyStateDescription('Create your first campaign: pick vehicles, preview it, and push it to Brevo as a draft.');
    }

    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->action(function (Campaign $record) {
                $copy = app(CampaignDuplicator::class)->duplicate($record, auth()->id());
                Notification::make()->title('Copy created. It is a new draft.')->success()->send();

                return redirect(static::getUrl('edit', ['record' => $copy]));
            });
    }

    public static function refreshBrevoAction(): Action
    {
        return Action::make('refreshBrevo')
            ->label('Refresh')
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function () {
                app(BrevoDirectory::class)->forget();
                Notification::make()->title('Senders and lists reloaded from Brevo.')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
