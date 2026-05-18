<?php

namespace App\Filament\Admin\Resources\Redirects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('from_path')
                    ->label('From path')
                    ->placeholder('old-cars/toyota-corolla')
                    ->helperText('The dead URL path — no domain, no leading slash. Matched against requests that would 404.')
                    ->required()
                    ->maxLength(500)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                TextInput::make('to_path')
                    ->label('Redirect to')
                    ->placeholder('/vehicles  or  https://example.com/page')
                    ->helperText('A path on this site, or a full URL.')
                    ->required()
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Select::make('status_code')
                    ->label('Redirect type')
                    ->options([301 => '301 — Permanent', 302 => '302 — Temporary'])
                    ->default(301)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
