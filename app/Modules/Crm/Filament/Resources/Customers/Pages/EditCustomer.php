<?php

namespace App\Modules\Crm\Filament\Resources\Customers\Pages;

use App\Modules\Crm\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['email'] = \App\Modules\Crm\Domain\CustomerMatcher::normalize($data['email'] ?? null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return CustomerResource::getUrl('view', ['record' => $this->record]);
    }
}
