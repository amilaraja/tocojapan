<?php

namespace App\Filament\Admin\Resources\Pages\Schemas;

use App\Cms\PageTemplateRegistry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')
                ->columns(3)
                ->schema([
                    Select::make('template_key')
                        ->label('Template')
                        ->options(PageTemplateRegistry::options())
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->helperText('Locked after creation. Make a new page to switch templates.'),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(180)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                            if (! $get('slug')) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')->required()->maxLength(220),
                    Select::make('locale')
                        ->options(['en' => 'English', 'ja' => 'Japanese'])
                        ->default('en')
                        ->required(),
                ]),

            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Content')
                    ->columns(2)
                    ->schema(function (Get $get): array {
                        $cls = PageTemplateRegistry::resolve((string) $get('template_key'));
                        if (! $cls) {
                            return [];
                        }

                        return $cls::fields();
                    }),

                Tab::make('SEO')
                    ->schema([
                        TextInput::make('seo_title')->maxLength(180),
                        Textarea::make('seo_description')->maxLength(500)->rows(3),
                        TextInput::make('seo_image')->label('SEO image URL')->url(),
                    ]),

                Tab::make('Publish')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->helperText('Optional: schedule a future publish.'),
                    ]),
            ]),
        ]);
    }
}
