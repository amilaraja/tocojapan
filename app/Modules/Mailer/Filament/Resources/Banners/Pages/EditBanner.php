<?php

namespace App\Modules\Mailer\Filament\Resources\Banners\Pages;

use App\Modules\Mailer\Filament\Resources\Banners\BannerResource;
use Filament\Resources\Pages\EditRecord;

class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
