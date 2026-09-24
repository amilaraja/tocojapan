<?php

namespace App\Modules\Mailer\Filament\Resources\ApprovedSenders;

use App\Modules\Mailer\Domain\Brevo\BrevoDirectory;
use App\Modules\Mailer\Domain\Importer\FieldRuleEngine;
use App\Modules\Mailer\Domain\Importer\SenderMatcher;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** S10 Approved senders (TOC-EXT-001, 006, 007, TOC-BRV-006, 007). */
class ApprovedSenderResource extends Resource
{
    protected static ?string $model = ApprovedSender::class;

    protected static ?string $cluster = Importer::class;

    protected static ?string $slug = 'senders';

    protected static ?string $navigationLabel = 'Approved senders';

    protected static ?string $modelLabel = 'approved sender';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sender')
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->label('Name')
                        ->helperText('Saved on each contact in Brevo as SOURCE, e.g. "Website inquiry form".')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('match_value')
                        ->label('Messages from')
                        ->helperText('An exact address (leads@portal.com) or a whole domain (@portal.com).')
                        ->required()
                        ->maxLength(190)
                        ->unique(ignoreRecord: true)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (SenderMatcher::typeOf((string) $value) === null) {
                                $fail('Enter an email address, or @ followed by a domain.');
                            }
                        }),
                    Toggle::make('active')->label('Import from this sender')->default(true),
                    TextInput::make('max_per_message')
                        ->label('Most addresses to take from one message')
                        ->numeric()->integer()->minValue(1)->maxValue(20)
                        ->default((int) config('mailer.import.default_max_per_message', 3))
                        ->required(),
                    Toggle::make('use_reply_to')
                        ->label('Also use the Reply-To address')
                        ->columnSpanFull(),
                ]),
            Section::make('Brevo lists and consent')
                ->schema([
                    Select::make('brevo_list_ids')
                        ->label('Add contacts to these Brevo lists')
                        ->multiple()
                        ->options(fn () => app(BrevoDirectory::class)->lists())
                        ->required()
                        ->helperText(fn () => app(BrevoDirectory::class)->lists() === []
                            ? 'No lists loaded. Connect Brevo in Mailer settings, then press Refresh.'
                            : 'Lists come from Brevo. Created a new one? Press Refresh.')
                        ->hintAction(
                            Action::make('refreshLists')
                                ->label('Refresh')
                                ->icon(Heroicon::OutlinedArrowPath)
                                ->action(function () {
                                    app(BrevoDirectory::class)->forget();
                                    Notification::make()->title('Lists reloaded from Brevo.')->success()->send();
                                }),
                        ),
                    Radio::make('consent_mode')
                        ->label('How to add new contacts')
                        ->options([
                            ApprovedSender::CONSENT_DIRECT => 'Add them straight to the lists',
                            ApprovedSender::CONSENT_CONFIRM => 'Ask them to confirm by email first (Brevo sends the confirmation)',
                        ])
                        ->default(ApprovedSender::CONSENT_DIRECT)
                        ->required()
                        ->live(),
                    TextInput::make('doi_template_id')
                        ->label('Brevo confirmation email (template number)')
                        ->numeric()->integer()->minValue(1)
                        ->visible(fn (Get $get) => $get('consent_mode') === ApprovedSender::CONSENT_CONFIRM)
                        ->required(fn (Get $get) => $get('consent_mode') === ApprovedSender::CONSENT_CONFIRM),
                    TextInput::make('doi_redirect_url')
                        ->label('Page shown after they confirm')
                        ->url()
                        ->visible(fn (Get $get) => $get('consent_mode') === ApprovedSender::CONSENT_CONFIRM)
                        ->required(fn (Get $get) => $get('consent_mode') === ApprovedSender::CONSENT_CONFIRM),
                ]),
            Section::make('Field rules')
                ->description('Optional. Pick up details from the message text. Put ( ) around the part to keep, for example  Name: (.+)  or  Country: (.+)')
                ->schema([
                    Repeater::make('field_rules')
                        ->label('')
                        ->columns(2)
                        ->schema([
                            Select::make('field')->label('Save as')->options(FieldRuleEngine::FIELDS)->required(),
                            TextInput::make('pattern')
                                ->label('Pattern')
                                ->required()
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! FieldRuleEngine::isValid((string) $value)) {
                                        $fail('This pattern does not work. Put ( ) around the part to keep, e.g. Name: (.+)');
                                    }
                                }),
                        ])
                        ->addActionLabel('Add a field rule')
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('label')->label('Name')->searchable()->sortable(),
                TextColumn::make('match_value')->label('Messages from')->searchable(),
                TextColumn::make('brevo_list_ids')
                    ->label('Brevo lists')
                    ->formatStateUsing(fn ($state) => app(BrevoDirectory::class)->lists()[(int) $state] ?? '#'.$state)
                    ->badge(),
                TextColumn::make('consent_mode')
                    ->label('Consent')
                    ->formatStateUsing(fn (string $state) => $state === ApprovedSender::CONSENT_CONFIRM ? 'Confirm first' : 'Direct')
                    ->badge()
                    ->color(fn (string $state) => $state === ApprovedSender::CONSENT_CONFIRM ? 'warning' : 'gray'),
                TextColumn::make('contact_imports_count')->label('Imports')->counts('contactImports'),
                ToggleColumn::make('active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No approved senders yet')
            ->emptyStateDescription('Add the addresses or domains whose messages may be read for buyer emails.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovedSenders::route('/'),
            'create' => Pages\CreateApprovedSender::route('/create'),
            'edit' => Pages\EditApprovedSender::route('/{record}/edit'),
        ];
    }
}
