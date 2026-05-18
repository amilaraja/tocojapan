<?php

namespace App\Filament\Admin\Resources\ImportRegulations\Pages;

use App\Filament\Admin\Resources\ImportRegulations\ImportRegulationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImportRegulation extends EditRecord
{
    protected static string $resource = ImportRegulationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
