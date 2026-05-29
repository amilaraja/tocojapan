<?php

namespace App\Filament\Admin\Resources\VehicleOptions\Pages;

use App\Filament\Admin\Resources\VehicleOptions\VehicleOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleOption extends EditRecord
{
    protected static string $resource = VehicleOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
