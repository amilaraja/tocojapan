<?php

namespace App\Filament\Admin\Resources\Quotes\RelationManagers;

use App\Models\Quote;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')->required()->rows(5)->columnSpanFull(),
                Toggle::make('is_internal')
                    ->label('Internal note (hidden from customer)')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('From'),
                TextColumn::make('body')->limit(80)->wrap()->searchable(),
                IconColumn::make('is_internal')->label('Internal')->boolean(),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Reply')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['user_id'] = auth()->id();

                        return $data;
                    })
                    ->after(function () {
                        /** @var Quote $quote */
                        $quote = $this->getOwnerRecord();
                        $quote->update(['last_admin_reply_at' => now()]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
