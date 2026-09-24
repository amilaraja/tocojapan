<?php

namespace App\Modules\Mailer\Filament\Resources\IgnoreRules;

use App\Modules\Mailer\Domain\Importer\SenderMatcher;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Models\IgnoreRule;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Ignore list (TOC-EXT-009): addresses or @domains never imported. */
class IgnoreRuleResource extends Resource
{
    protected static ?string $model = IgnoreRule::class;

    protected static ?string $cluster = Importer::class;

    protected static ?string $slug = 'ignore-list';

    protected static ?string $navigationLabel = 'Ignore list';

    protected static ?string $modelLabel = 'ignored address';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('value')
                ->label('Address or @domain')
                ->helperText('For example sales@competitor.jp, or @competitor.jp for the whole domain.')
                ->required()
                ->maxLength(190)
                ->unique(ignoreRecord: true)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (SenderMatcher::typeOf((string) $value) === null) {
                        $fail('Enter an email address, or @ followed by a domain.');
                    }
                }),
            TextInput::make('note')->label('Note (optional)')->maxLength(255),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('value')
            ->columns([
                TextColumn::make('value')->label('Address or domain')->searchable()->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => $state === 'domain' ? 'Whole domain' : 'Address'),
                TextColumn::make('note')->placeholder('—')->wrap(),
                TextColumn::make('created_at')->label('Added')->date('j M Y')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()])
            ->emptyStateHeading('Nothing on the ignore list');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageIgnoreRules::route('/')];
    }
}
