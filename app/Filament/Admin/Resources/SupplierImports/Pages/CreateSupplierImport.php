<?php

namespace App\Filament\Admin\Resources\SupplierImports\Pages;

use App\Filament\Admin\Resources\SupplierImports\SupplierImportResource;
use App\Jobs\RunSupplierImport;
use App\Models\Supplier;
use App\Models\SupplierImport;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateSupplierImport extends CreateRecord
{
    protected static string $resource = SupplierImportResource::class;

    protected static ?string $title = 'Import supplier stock';

    protected static bool $canCreateAnother = false;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $supplier = Supplier::query()->findOrFail($data['supplier_id']);

        if (SupplierImport::query()->where('supplier_id', $supplier->id)
            ->whereIn('status', [SupplierImport::STATUS_QUEUED, SupplierImport::STATUS_STAGING, SupplierImport::STATUS_PREVIEWING, SupplierImport::STATUS_PREVIEWED, SupplierImport::STATUS_APPLYING, SupplierImport::STATUS_DELISTING])
            ->exists()) {
            throw ValidationException::withMessages([
                'data.supplier_id' => 'This supplier already has an import in progress or waiting for review. Finish or cancel it first.',
            ]);
        }

        if (($data['source'] ?? 'upload') === 'inbox') {
            $name = basename((string) $data['inbox_file']);
            $inbox = SupplierImportResource::inboxPath($supplier).'/'.$name;
            if (! is_file($inbox)) {
                throw ValidationException::withMessages(['data.inbox_file' => 'That file is no longer in the inbox.']);
            }
            // Move it out of the inbox so it is not imported twice.
            $relative = 'supplier-imports/'.$supplier->slug.'/'.now()->format('Ymd-His').'-'.$name;
            Storage::disk('local')->makeDirectory(dirname($relative));
            rename($inbox, Storage::disk('local')->path($relative));
            $original = $name;
        } else {
            $relative = (string) $data['upload'];
            $original = basename($relative);
        }

        $import = SupplierImport::query()->create([
            'supplier_id' => $supplier->id,
            'user_id' => auth()->id(),
            'status' => SupplierImport::STATUS_QUEUED,
            'mode' => $data['mode'] === 'partial' ? 'partial' : 'full',
            'original_name' => $original,
            'file_path' => Storage::disk('local')->path($relative),
            'stats' => ['margin_override' => array_filter([
                'percent' => $data['margin_percent'] ?? null,
                'fixed_usd' => $data['margin_fixed_usd'] ?? null,
            ], fn ($v) => $v !== null && $v !== '') ?: null],
        ]);

        RunSupplierImport::dispatch($import->id);

        return $import;
    }

    protected function getRedirectUrl(): string
    {
        return SupplierImportResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'File received — reading and comparing it now. You will review the changes before anything goes live.';
    }
}
