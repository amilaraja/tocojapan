<?php

namespace App\Filament\Admin\Resources\ContactInquiries\Pages;

use App\Filament\Admin\Resources\ContactInquiries\ContactInquiryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContactInquiries extends ListRecords
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
