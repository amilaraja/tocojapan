<?php

namespace App\Filament\Admin\Resources\VehicleModels\Pages;

use App\Filament\Admin\Resources\VehicleModels\VehicleModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleModel extends EditRecord
{
    protected static string $resource = VehicleModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
