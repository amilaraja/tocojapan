<?php

namespace App\Modules\Crm\Filament\Resources\Templates\Pages;

use App\Modules\Crm\Filament\Resources\Templates\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;
}
