<?php

namespace App\Modules\Crm\Filament\Resources\Templates\Pages;

use App\Modules\Crm\Filament\Resources\Templates\EmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('New template')];
    }
}
