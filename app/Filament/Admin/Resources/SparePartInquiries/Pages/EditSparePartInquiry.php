<?php

namespace App\Filament\Admin\Resources\SparePartInquiries\Pages;

use App\Filament\Admin\Resources\SparePartInquiries\SparePartInquiryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSparePartInquiry extends EditRecord
{
    protected static string $resource = SparePartInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
