<?php

namespace App\Modules\Mailer\Filament\Resources\ImportRuns\Pages;

use App\Modules\Mailer\Filament\Resources\ImportRuns\ImportRunResource;
use Filament\Resources\Pages\ManageRecords;

class ManageImportRuns extends ManageRecords
{
    protected static string $resource = ImportRunResource::class;
}
