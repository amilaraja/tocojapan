<?php

namespace App\Modules\Crm\Filament\Resources\Queues\Pages;

use App\Modules\Crm\Filament\Resources\Queues\QueueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQueues extends ListRecords
{
    protected static string $resource = QueueResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
