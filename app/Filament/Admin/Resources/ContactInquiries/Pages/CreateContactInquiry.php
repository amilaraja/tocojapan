<?php

namespace App\Filament\Admin\Resources\ContactInquiries\Pages;

use App\Filament\Admin\Resources\ContactInquiries\ContactInquiryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContactInquiry extends CreateRecord
{
    protected static string $resource = ContactInquiryResource::class;
}
