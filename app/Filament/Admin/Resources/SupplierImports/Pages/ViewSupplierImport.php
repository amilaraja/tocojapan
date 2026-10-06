<?php

namespace App\Filament\Admin\Resources\SupplierImports\Pages;

use App\Filament\Admin\Resources\SupplierImports\SupplierImportResource;
use App\Jobs\RunSupplierImport;
use App\Models\SupplierImport;
use App\Suppliers\SupplierImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ViewSupplierImport extends ViewRecord
{
    protected static string $resource = SupplierImportResource::class;

    protected string $view = 'filament.admin.supplier-imports.view';

    public function getTitle(): string
    {
        return 'Stock import #'.$this->record->id;
    }

    protected function getHeaderActions(): array
    {
        /** @var SupplierImport $import */
        $import = $this->record;

        return [
            Action::make('approve')
                ->label('Approve & apply changes')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->refresh()->isAwaitingApproval())
                ->requiresConfirmation()
                ->modalHeading('Apply this import to the website?')
                ->modalDescription(fn () => $this->approvalSummary())
                ->schema(fn () => $this->record->needsDelistConfirmation() ? [
                    Checkbox::make('confirm_delist')
                        ->label('I checked the file — delist '.number_format((int) $this->record->stat('to_delist')).' vehicles anyway.')
                        ->accepted(),
                ] : [])
                ->action(function (array $data) {
                    try {
                        app(SupplierImporter::class)->approve($this->record, (bool) ($data['confirm_delist'] ?? false));
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    RunSupplierImport::dispatch($this->record->id);
                    Notification::make()->title('Applying — the stock updates over the next few minutes.')->success()->send();
                }),
            Action::make('cancel')
                ->label('Discard import')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => in_array($this->record->refresh()->status, [SupplierImport::STATUS_QUEUED, SupplierImport::STATUS_STAGING, SupplierImport::STATUS_PREVIEWING, SupplierImport::STATUS_PREVIEWED], true))
                ->requiresConfirmation()
                ->modalDescription('Nothing on the website changes. The file can be imported again later.')
                ->action(function () {
                    app(SupplierImporter::class)->cancel($this->record);
                    Notification::make()->title('Import discarded')->send();
                }),
            Action::make('retry')
                ->label('Retry')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->record->refresh()->status === SupplierImport::STATUS_FAILED && $this->record->approved_at === null)
                ->action(function () {
                    $this->record->forceFill(['status' => SupplierImport::STATUS_QUEUED, 'message' => null, 'finished_at' => null])->save();
                    RunSupplierImport::dispatch($this->record->id);
                }),
            Action::make('resume')
                ->label('Resume applying')
                ->icon('heroicon-o-play')
                ->visible(fn () => $this->record->refresh()->status === SupplierImport::STATUS_FAILED && $this->record->approved_at !== null)
                ->requiresConfirmation()
                ->modalDescription('Continues from the last saved batch; vehicles already written are not touched twice.')
                ->action(function () {
                    $this->record->forceFill(['status' => SupplierImport::STATUS_APPLYING, 'message' => null, 'finished_at' => null])->save();
                    RunSupplierImport::dispatch($this->record->id);
                }),
            Action::make('vehicles')
                ->label('View vehicles')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->visible(fn () => $this->record->status === SupplierImport::STATUS_COMPLETED)
                ->url(fn () => route('filament.admin.resources.vehicles.index', ['filters' => ['supplier_id' => ['value' => $import->supplier_id]]])),
        ];
    }

    protected function approvalSummary(): string
    {
        $i = $this->record;
        $c = $i->stat('counts', []);

        return sprintf(
            '%s new, %s updated (%s price changes), %s back in stock%s. Vehicle links stay the same.',
            number_format($c['new'] ?? 0),
            number_format($c['update'] ?? 0),
            number_format(($c['price_up'] ?? 0) + ($c['price_down'] ?? 0)),
            number_format($c['relist'] ?? 0),
            $i->mode === 'full' ? ', '.number_format((int) $i->stat('to_delist')).' delisted' : '',
        );
    }

    /** @return Collection<int, object> */
    public function priceChanges(): Collection
    {
        return DB::table('supplier_import_rows as r')
            ->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->where('r.supplier_import_id', $this->record->id)
            ->where('r.action', 'update')
            ->whereColumn('r.old_price_fob', '!=', 'r.new_price_fob')
            ->orderByRaw('ABS(r.new_price_fob - r.old_price_fob) DESC')
            ->limit(25)
            ->get(['v.id', 'v.title', 'v.stock_no', 'v.slug', 'r.old_price_fob', 'r.new_price_fob']);
    }

    /** @return Collection<int, object> */
    public function newSample(): Collection
    {
        return DB::table('supplier_import_rows')
            ->where('supplier_import_id', $this->record->id)
            ->where('action', 'new')
            ->orderBy('id')
            ->limit(15)
            ->get(['supplier_ref', 'payload', 'new_price_fob'])
            ->map(function ($r) {
                $p = json_decode((string) $r->payload, true) ?: [];
                $r->title = trim(($p['year'] ?? '').' '.($p['make'] ?? '').' '.($p['model'] ?? ''));

                return $r;
            });
    }

    /** @return Collection<int, object> */
    public function delistSample(): Collection
    {
        if ($this->record->mode !== 'full' || ! $this->record->isAwaitingApproval()) {
            return collect();
        }

        return DB::table('vehicles as v')
            ->where('v.supplier_id', $this->record->supplier_id)
            ->where('v.status', 'published')
            ->whereNull('v.deleted_at')
            ->whereNotExists(fn ($q) => $q->from('supplier_import_rows as r')
                ->where('r.supplier_import_id', $this->record->id)
                ->whereColumn('r.supplier_ref', 'v.supplier_ref'))
            ->orderBy('v.id')
            ->limit(15)
            ->get(['v.id', 'v.title', 'v.stock_no', 'v.slug', 'v.price_fob']);
    }
}
