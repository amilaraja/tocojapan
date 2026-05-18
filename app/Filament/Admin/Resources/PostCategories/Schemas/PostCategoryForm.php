<?php

namespace App\Filament\Admin\Resources\PostCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PostCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('slug')
                    ->maxLength(120)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave empty to generate from the name automatically.'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
