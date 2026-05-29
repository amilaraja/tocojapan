<?php

namespace App\Filament\Admin\Resources\VehicleOptions\Pages;

use App\Filament\Admin\Resources\VehicleOptions\VehicleOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicleOptions extends ListRecords
{
    protected static string $resource = VehicleOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
