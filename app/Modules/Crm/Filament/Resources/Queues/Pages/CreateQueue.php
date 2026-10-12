<?php

namespace App\Modules\Crm\Filament\Resources\Queues\Pages;

use App\Modules\Crm\Filament\Resources\Queues\QueueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQueue extends CreateRecord
{
    protected static string $resource = QueueResource::class;
}
