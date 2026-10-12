<?php

namespace App\Modules\Crm\Filament\Resources\Steps\Pages;

use App\Modules\Crm\Filament\Resources\Steps\StepTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditStep extends EditRecord
{
    protected static string $resource = StepTemplateResource::class;
}
