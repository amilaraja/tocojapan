<?php

namespace App\Modules\Mailer\Filament\Resources\Campaigns\Pages;

use App\Models\BodyType;
use App\Models\Make;
use App\Modules\Mailer\Domain\Campaigns\CampaignPusher;
use App\Modules\Mailer\Domain\Campaigns\CampaignRenderer;
use App\Modules\Mailer\Domain\Campaigns\PushRefused;
use App\Modules\Mailer\Domain\Campaigns\VehicleRecheck;
use App\Modules\Mailer\Domain\Vehicles\VehicleDTO;
use App\Modules\Mailer\Domain\Vehicles\VehicleSource;
use App\Modules\Mailer\Filament\Resources\Campaigns\CampaignResource;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Models\CampaignVehicle;
use App\Modules\Mailer\Support\MailerAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * S4 Campaign Builder. Vehicles live in form state (id only) with their
 * snapshots in $vehicleSnapshots; saving writes mailer_campaign_vehicles in
 * the shown order. Preview and push both call CampaignRenderer::render()
 * on the saved campaign, so they are byte-identical (TOC-CB-003).
 */
class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected static ?string $title = 'Campaign builder';

    /** @var array<int, array<string, mixed>> vehicle id => VehicleDTO array */
    public array $vehicleSnapshots = [];

    /** @var list<array<string, mixed>> re-check notices (TOC-CB-004/005) */
    public array $issues = [];

    public function getMaxContentWidth(): Width
    {
        return Width::SevenExtraLarge;
    }

    /** Re-check on open (TOC-CB-004): refresh snapshots, collect notices. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->issues = app(CampaignPusher::class)->recheck($this->getRecord());
        $this->loadVehicles($data);

        return $data;
    }

    protected function loadVehicles(array &$data): void
    {
        $this->vehicleSnapshots = [];
        $data['vehicles'] = [];

        foreach ($this->getRecord()->vehicles()->get() as $row) {
            $this->vehicleSnapshots[$row->vehicle_id] = $row->snapshot;
            $data['vehicles'][(string) Str::uuid()] = ['id' => $row->vehicle_id];
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Campaign $record */
        $ordered = array_values(array_filter(array_map(fn ($v) => (int) ($v['id'] ?? 0), $data['vehicles'] ?? [])));
        unset($data['vehicles']);

        DB::transaction(function () use ($record, $data, $ordered) {
            $record->update($data);

            $existing = $record->vehicles()->get()->keyBy('vehicle_id');
            $record->vehicles()->whereNotIn('vehicle_id', $ordered ?: [0])->delete();

            foreach ($ordered as $position => $vehicleId) {
                $snapshot = $this->vehicleSnapshots[$vehicleId] ?? $existing[$vehicleId]?->snapshot;
                if (! $snapshot) {
                    continue;
                }
                CampaignVehicle::query()->updateOrCreate(
                    ['campaign_id' => $record->id, 'vehicle_id' => $vehicleId],
                    [
                        'stock_ref' => $snapshot['stockRef'] ?? null,
                        'position' => $position,
                        'snapshot' => $snapshot,
                        'added_at' => $existing[$vehicleId]?->added_at ?? now(),
                    ],
                );
            }
        });

        // TOC-CB-006: editing after a push → "Changed since push".
        app(CampaignPusher::class)->markChangedIfEdited($record);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addVehiclesAction(),
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->replaceMountedAction('showPreview');
                }),
            $this->pushAction(),
            Action::make('openInBrevo')
                ->label('Open in Brevo')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn () => (bool) $this->getRecord()->brevo_campaign_id)
                ->url(fn () => app(CampaignPusher::class)->brevoUrl($this->getRecord()), shouldOpenInNewTab: true),
            ActionGroup::make([
                CampaignResource::duplicateAction()->record($this->getRecord()),
                Action::make('archive')
                    ->label(fn () => $this->getRecord()->status === Campaign::STATUS_ARCHIVED ? 'Restore' : 'Archive')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->action(function () {
                        $r = $this->getRecord();
                        $r->forceFill(['status' => $r->status === Campaign::STATUS_ARCHIVED
                            ? ($r->brevo_campaign_id ? Campaign::STATUS_IN_BREVO : Campaign::STATUS_DRAFT)
                            : Campaign::STATUS_ARCHIVED])->save();
                    }),
                DeleteAction::make()->visible(fn () => ! $this->getRecord()->brevo_campaign_id),
            ]),
        ];
    }

    /** S5 Preview: the real email in an iframe at 600 or 375 px. */
    public function showPreviewAction(): Action
    {
        return Action::make('showPreview')
            ->modalHeading('Preview')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn () => view('mailer::filament.preview', [
                'html' => app(CampaignRenderer::class)->render($this->getRecord()->fresh()),
            ]));
    }

    protected function addVehiclesAction(): Action
    {
        $max = (int) config('mailer.campaign.max_vehicles', 12);

        return Action::make('addVehicles')
            ->label('Add vehicles')
            ->icon(Heroicon::OutlinedPlus)
            ->disabled(fn () => count($this->data['vehicles'] ?? []) >= $max)
            ->tooltip(fn () => count($this->data['vehicles'] ?? []) >= $max ? "{$max} vehicles is the most one email can show." : null)
            ->modalHeading('Add vehicles')
            ->modalDescription('Only vehicles for sale on tocojapan.com are shown. Sold vehicles never appear.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Add')
            ->schema([
                Grid::make(3)->schema([
                    Select::make('make')->label('Make')->options(fn () => Make::query()->orderBy('name')->pluck('name', 'slug'))->searchable()->live(),
                    Select::make('body_type')->label('Body type')->options(fn () => BodyType::query()->orderBy('name')->pluck('name', 'slug'))->live(),
                    Select::make('badge')->label('Badge')->options([VehicleDTO::BADGE_HOT_DEAL => 'Hot deal', VehicleDTO::BADGE_NEW => 'New'])->live(),
                    TextInput::make('price_from')->label('Price from (USD)')->numeric()->live(debounce: 600),
                    TextInput::make('price_to')->label('Price to (USD)')->numeric()->live(debounce: 600),
                    Toggle::make('include_reserved')->label('Include reserved')->visible(fn () => MailerAccess::isAdmin())->live()->inline(false),
                ]),
                Select::make('vehicle_ids')
                    ->label('Vehicles')
                    ->helperText('Type a stock number (e.g. E02056) or words from the title. Up to 20 results are shown; narrow them with the filters above.')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->options(fn (Get $get) => $this->vehicleOptions($get, null))
                    ->getSearchResultsUsing(fn (string $search, Get $get) => $this->vehicleOptions($get, $search))
                    ->getOptionLabelsUsing(fn (array $values) => collect(app(VehicleSource::class)->findMany(array_map('intval', $values)))
                        ->mapWithKeys(fn (VehicleDTO $v) => [$v->id => $this->optionLabel($v)])->all()),
            ])
            ->action(function (array $data) use ($max) {
                $source = app(VehicleSource::class);
                $added = 0;
                $current = array_map(fn ($v) => (int) $v['id'], $this->data['vehicles'] ?? []);

                foreach ($data['vehicle_ids'] as $id) {
                    $id = (int) $id;
                    if (in_array($id, $current, true)) {
                        continue;
                    }
                    if (count($current) >= $max) {
                        Notification::make()->title("A campaign can have up to {$max} vehicles.")->warning()->send();
                        break;
                    }
                    $dto = $source->find($id);
                    if (! $dto?->isSelectable()) {
                        continue;
                    }

                    $this->vehicleSnapshots[$id] = $dto->toArray();
                    $this->data['vehicles'][(string) Str::uuid()] = ['id' => $id];
                    $current[] = $id;
                    $added++;
                }

                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $this->fillForm();

                if ($added > 0) {
                    Notification::make()->title($added === 1 ? 'Vehicle added.' : "{$added} vehicles added.")->success()->send();
                }
            });
    }

    protected function pushAction(): Action
    {
        $min = (int) config('mailer.campaign.min_vehicles', 2);

        return Action::make('push')
            ->label('Push to Brevo as draft')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->disabled(fn () => count($this->data['vehicles'] ?? []) < $min)
            ->tooltip(fn () => count($this->data['vehicles'] ?? []) < $min ? "Add at least {$min} vehicles before pushing." : null)
            ->requiresConfirmation()
            ->modalHeading('Push to Brevo as draft?')
            ->modalDescription('This creates a draft in Brevo. Nothing is sent. Open Brevo to send a test, check it, then send.')
            ->modalSubmitActionLabel('Push to Brevo as draft')
            ->action(function () {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $campaign = $this->getRecord();

                try {
                    app(CampaignPusher::class)->push($campaign);
                } catch (PushRefused $e) {
                    $this->issues = $e->issues ?: $this->issues;
                    $notification = Notification::make()->title('Not pushed')->body($e->getMessage())->danger()->persistent();
                    if ($e->duplicate) {
                        $notification->actions([
                            Action::make('duplicate')->label('Duplicate this campaign')->button()
                                ->url(CampaignResource::getUrl('index')),
                        ]);
                    }
                    $notification->send();
                    $this->fillForm();
                    $this->issues = $e->issues ?: $this->issues;

                    return;
                }

                $this->fillForm();

                Notification::make()
                    ->title('Draft created in Brevo. Nothing has been sent yet.')
                    ->body('Open Brevo to send a test, check it, then send.')
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('open')->label('Open in Brevo')->button()
                            ->url(app(CampaignPusher::class)->brevoUrl($campaign), shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    /** @return array<int, string> */
    protected function vehicleOptions(Get $get, ?string $search): array
    {
        $page = app(VehicleSource::class)->search(array_filter([
            'q' => $search,
            'make' => $get('make'),
            'body_type' => $get('body_type'),
            'badge' => $get('badge'),
            'price_from' => $get('price_from'),
            'price_to' => $get('price_to'),
        ]), 1, includeReserved: MailerAccess::isAdmin() && (bool) $get('include_reserved'));

        return collect($page->items())->mapWithKeys(fn (VehicleDTO $v) => [$v->id => $this->optionLabel($v)])->all();
    }

    protected function optionLabel(VehicleDTO $v): string
    {
        return implode(' · ', array_filter([
            $v->stockRef,
            $v->title,
            VehicleRecheck::money($v->priceFob),
            $v->badgeLabel(),
        ]));
    }
}
