<?php

namespace App\Modules\Crm\Filament\Resources\Steps\Pages;

use App\Modules\Crm\Filament\Resources\Steps\StepTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListSteps extends ListRecords
{
    protected static string $resource = StepTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('New mini step')];
    }
}
