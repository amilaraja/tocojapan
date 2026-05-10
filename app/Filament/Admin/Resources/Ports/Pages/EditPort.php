<?php

namespace App\Filament\Admin\Resources\Ports\Pages;

use App\Filament\Admin\Resources\Ports\PortResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPort extends EditRecord
{
    protected static string $resource = PortResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
