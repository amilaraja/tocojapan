<?php

namespace App\Filament\Admin\Resources\SparePartInquiries\Pages;

use App\Filament\Admin\Resources\SparePartInquiries\SparePartInquiryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSparePartInquiries extends ListRecords
{
    protected static string $resource = SparePartInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
