<?php

namespace App\Filament\Admin\Resources\ImportRegulations\Pages;

use App\Filament\Admin\Resources\ImportRegulations\ImportRegulationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImportRegulations extends ListRecords
{
    protected static string $resource = ImportRegulationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
