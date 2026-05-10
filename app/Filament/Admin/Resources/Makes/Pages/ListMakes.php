<?php

namespace App\Filament\Admin\Resources\Makes\Pages;

use App\Filament\Admin\Resources\Makes\MakeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMakes extends ListRecords
{
    protected static string $resource = MakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
