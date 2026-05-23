<?php

namespace App\Filament\Admin\Resources\Subscribers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('source')
                    ->default(null),
                TextInput::make('ip')
                    ->default(null),
                TextInput::make('user_agent')
                    ->default(null),
                DateTimePicker::make('unsubscribed_at'),
            ]);
    }
}
