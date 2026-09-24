<?php

namespace App\Modules\Mailer\Filament\Resources\ApprovedSenders\Pages;

use App\Modules\Mailer\Filament\Resources\ApprovedSenders\ApprovedSenderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApprovedSender extends EditRecord
{
    protected static string $resource = ApprovedSenderResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
