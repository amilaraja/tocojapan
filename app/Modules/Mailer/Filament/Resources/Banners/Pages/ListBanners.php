<?php

namespace App\Modules\Mailer\Filament\Resources\Banners\Pages;

use App\Modules\Mailer\Filament\Resources\Banners\BannerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBanners extends ListRecords
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Upload banner')];
    }
}
