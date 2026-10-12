<?php

namespace App\Modules\Crm\Filament\Resources\Templates\Pages;

use App\Modules\Crm\Filament\Resources\Templates\EmailTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;
}
