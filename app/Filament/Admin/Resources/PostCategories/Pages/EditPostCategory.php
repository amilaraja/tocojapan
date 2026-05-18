<?php

namespace App\Filament\Admin\Resources\PostCategories\Pages;

use App\Filament\Admin\Resources\PostCategories\PostCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPostCategory extends EditRecord
{
    protected static string $resource = PostCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
