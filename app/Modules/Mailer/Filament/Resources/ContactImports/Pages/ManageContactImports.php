<?php

namespace App\Modules\Mailer\Filament\Resources\ContactImports\Pages;

use App\Modules\Mailer\Filament\Resources\ContactImports\ContactImportResource;
use Filament\Resources\Pages\ManageRecords;

class ManageContactImports extends ManageRecords
{
    protected static string $resource = ContactImportResource::class;
}
