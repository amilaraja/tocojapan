<?php

namespace App\Filament\Admin\Resources\PostCategories;

use App\Filament\Admin\Resources\PostCategories\Pages\CreatePostCategory;
use App\Filament\Admin\Resources\PostCategories\Pages\EditPostCategory;
use App\Filament\Admin\Resources\PostCategories\Pages\ListPostCategories;
use App\Filament\Admin\Resources\PostCategories\Schemas\PostCategoryForm;
use App\Filament\Admin\Resources\PostCategories\Tables\PostCategoriesTable;
use App\Models\PostCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PostCategoryResource extends Resource
{
    protected static ?string $model = PostCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'News categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PostCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostCategories::route('/'),
            'create' => CreatePostCategory::route('/create'),
            'edit' => EditPostCategory::route('/{record}/edit'),
        ];
    }
}
