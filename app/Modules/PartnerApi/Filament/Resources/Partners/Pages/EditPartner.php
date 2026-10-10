<?php

namespace App\Modules\PartnerApi\Filament\Resources\Partners\Pages;

use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerResource;
use Filament\Resources\Pages\EditRecord;

class EditPartner extends EditRecord
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PartnerResource::suspendAction(),
            PartnerResource::reactivateAction(),
            PartnerResource::revokeAction(),
        ];
    }
}
