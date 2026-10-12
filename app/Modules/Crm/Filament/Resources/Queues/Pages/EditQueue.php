<?php

namespace App\Modules\Crm\Filament\Resources\Queues\Pages;

use App\Modules\Crm\Filament\Resources\Queues\QueueResource;
use Filament\Resources\Pages\EditRecord;

class EditQueue extends EditRecord
{
    protected static string $resource = QueueResource::class;
}
