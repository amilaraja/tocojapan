<?php

namespace App\Filament\Admin\Resources\NotFoundLogs\Pages;

use App\Filament\Admin\Resources\NotFoundLogs\NotFoundLogResource;
use Filament\Resources\Pages\ListRecords;

class ListNotFoundLogs extends ListRecords
{
    protected static string $resource = NotFoundLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
