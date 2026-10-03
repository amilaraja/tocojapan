<?php

namespace App\Modules\Mailer\Filament\Resources\Banners\Pages;

use App\Modules\Mailer\Domain\Campaigns\BannerImages;
use App\Modules\Mailer\Filament\Resources\Banners\BannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;

    protected static ?string $title = 'Upload banner';

    /** Replace the upload with the optimised JPEG, 1200 px wide and never cropped (TOC-BAN-002). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, ...app(BannerImages::class)->optimise($data['path'])];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
