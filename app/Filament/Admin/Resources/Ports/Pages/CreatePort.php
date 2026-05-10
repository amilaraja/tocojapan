<?php

namespace App\Filament\Admin\Resources\Ports\Pages;

use App\Filament\Admin\Resources\Ports\PortResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePort extends CreateRecord
{
    protected static string $resource = PortResource::class;
}
