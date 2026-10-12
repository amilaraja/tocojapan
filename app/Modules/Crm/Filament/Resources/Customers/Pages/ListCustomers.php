<?php

namespace App\Modules\Crm\Filament\Resources\Customers\Pages;

use App\Modules\Crm\Filament\Concerns\HasSavedViews;
use App\Modules\Crm\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    use HasSavedViews;

    protected static string $resource = CustomerResource::class;

    protected function savedViewList(): string
    {
        return 'customers';
    }

    protected function getHeaderActions(): array
    {
        return $this->savedViewActions();
    }
}
