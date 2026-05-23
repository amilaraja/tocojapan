<?php

namespace App\Filament\Admin\Resources\Subscribers\Pages;

use App\Filament\Admin\Resources\Subscribers\SubscriberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriber extends CreateRecord
{
    protected static string $resource = SubscriberResource::class;
}
