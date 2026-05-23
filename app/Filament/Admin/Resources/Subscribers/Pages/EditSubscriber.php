<?php

namespace App\Filament\Admin\Resources\Subscribers\Pages;

use App\Filament\Admin\Resources\Subscribers\SubscriberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriber extends EditRecord
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
