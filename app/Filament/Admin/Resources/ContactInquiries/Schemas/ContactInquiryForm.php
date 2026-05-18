<?php

namespace App\Filament\Admin\Resources\ContactInquiries\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContactInquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->default(null),
                TextInput::make('subject')
                    ->default(null),
                Textarea::make('message')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('ip')
                    ->default(null),
                TextInput::make('user_agent')
                    ->default(null),
                Toggle::make('is_handled')
                    ->required(),
                DateTimePicker::make('handled_at'),
            ]);
    }
}
