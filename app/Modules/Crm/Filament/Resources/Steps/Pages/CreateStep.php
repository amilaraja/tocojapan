<?php

namespace App\Modules\Crm\Filament\Resources\Steps\Pages;

use App\Modules\Crm\Filament\Resources\Steps\StepTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStep extends CreateRecord
{
    protected static string $resource = StepTemplateResource::class;
}
