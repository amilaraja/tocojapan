<?php

namespace App\Modules\Mailer\Filament\Resources\ApprovedSenders\Pages;

use App\Modules\Mailer\Filament\Resources\ApprovedSenders\ApprovedSenderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovedSenders extends ListRecords
{
    protected static string $resource = ApprovedSenderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add sender')];
    }
}
