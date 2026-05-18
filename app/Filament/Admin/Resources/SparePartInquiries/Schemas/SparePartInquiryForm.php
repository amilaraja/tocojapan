<?php

namespace App\Filament\Admin\Resources\SparePartInquiries\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SparePartInquiryForm
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
                    ->required(),
                TextInput::make('country')
                    ->default(null),
                TextInput::make('address')
                    ->default(null),
                TextInput::make('model_name')
                    ->default(null),
                TextInput::make('chassis_no')
                    ->default(null),
                TextInput::make('year')
                    ->default(null),
                TextInput::make('engine_model')
                    ->default(null),
                TextInput::make('condition')
                    ->default(null),
                TextInput::make('shipping_method')
                    ->default(null),
                Textarea::make('parts_description')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('attachments')
                    ->default(null)
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
