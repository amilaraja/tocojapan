<?php

namespace App\Filament\Admin\Resources\PostCategories\Pages;

use App\Filament\Admin\Resources\PostCategories\PostCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPostCategories extends ListRecords
{
    protected static string $resource = PostCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
