<?php

namespace App\Filament\Admin\Resources\Makes\Pages;

use App\Filament\Admin\Resources\Makes\MakeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMake extends EditRecord
{
    protected static string $resource = MakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
