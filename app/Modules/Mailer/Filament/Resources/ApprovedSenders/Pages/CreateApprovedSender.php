<?php

namespace App\Modules\Mailer\Filament\Resources\ApprovedSenders\Pages;

use App\Modules\Mailer\Filament\Resources\ApprovedSenders\ApprovedSenderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApprovedSender extends CreateRecord
{
    protected static string $resource = ApprovedSenderResource::class;
}
