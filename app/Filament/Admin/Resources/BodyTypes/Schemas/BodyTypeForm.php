<?php

namespace App\Filament\Admin\Resources\BodyTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BodyTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('icon_path'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
