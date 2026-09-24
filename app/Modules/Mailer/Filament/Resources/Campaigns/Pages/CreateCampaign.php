<?php

namespace App\Modules\Mailer\Filament\Resources\Campaigns\Pages;

use App\Modules\Mailer\Filament\Resources\Campaigns\CampaignResource;
use App\Modules\Mailer\Models\Campaign;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected static ?string $title = 'New campaign';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => Campaign::STATUS_DRAFT, 'created_by' => auth()->id()];
    }

    /** Straight into the builder to add vehicles. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
