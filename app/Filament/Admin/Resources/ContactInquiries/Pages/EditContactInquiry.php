<?php

namespace App\Filament\Admin\Resources\ContactInquiries\Pages;

use App\Filament\Admin\Resources\ContactInquiries\ContactInquiryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContactInquiry extends EditRecord
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
