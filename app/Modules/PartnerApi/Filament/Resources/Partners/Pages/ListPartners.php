<?php

namespace App\Modules\PartnerApi\Filament\Resources\Partners\Pages;

use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add partner')];
    }
}
