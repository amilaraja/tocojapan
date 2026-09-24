<?php

namespace App\Modules\Mailer\Filament\Resources\ContactImports;

use App\Modules\Mailer\Domain\Brevo\ContactSync;
use App\Modules\Mailer\Domain\Importer\Reasons;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

/** S12 Contact search / audit (TOC-LOG-002), with Retry for failed imports (TOC-BRV-005). */
class ContactImportResource extends Resource
{
    protected static ?string $model = ContactImport::class;

    protected static ?string $cluster = Importer::class;

    protected static ?string $slug = 'contact-search';

    protected static ?string $navigationLabel = 'Contact search';

    protected static ?string $modelLabel = 'contact import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search by email address')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('approvedSender.label')->label('From sender')->placeholder('—'),
                TextColumn::make('outcome')->label('Result')->badge()
                    ->formatStateUsing(fn (string $state) => Reasons::label($state))
                    ->color(fn (string $state) => ['added' => 'success', 'updated' => 'info', 'failed' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('reason')->formatStateUsing(fn (?string $state) => Reasons::label($state))->placeholder('—'),
                TextColumn::make('fields')->label('Details found')
                    ->state(fn (ContactImport $r) => collect($r->fields ?? [])->map(fn ($v, $k) => ucfirst(strtolower(str_replace('_', ' ', $k))).': '.$v)->values()->all())
                    ->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('brevo_status_code')->label('Brevo code')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gmail_message_id')->label('Message id')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('outcome')->label('Result')->options(collect(['added', 'updated', 'skipped', 'failed'])->mapWithKeys(fn ($o) => [$o => Reasons::label($o)])),
                SelectFilter::make('reason')->options(collect(Reasons::LABELS)->except(['added', 'updated', 'skipped', 'failed'])),
                SelectFilter::make('approved_sender_id')->label('Sender')->relationship('approvedSender', 'label'),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (ContactImport $r) => $r->outcome === ContactImport::OUTCOME_FAILED && $r->approved_sender_id !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Send this address to Brevo again.')
                    ->action(function (ContactImport $record) {
                        $sender = ApprovedSender::find($record->approved_sender_id);
                        try {
                            $result = app(ContactSync::class)->sync($record->email, $record->fields ?? [], $sender);
                        } catch (Throwable $e) {
                            Notification::make()->title('Brevo could not be reached. Try again later.')->danger()->send();

                            return;
                        }

                        ContactImport::create([
                            'email' => $record->email, 'gmail_message_id' => $record->gmail_message_id,
                            'approved_sender_id' => $sender->id, 'outcome' => $result->outcome, 'reason' => $result->reason,
                            'brevo_status_code' => $result->status, 'fields' => $record->fields, 'created_at' => now(),
                        ]);

                        $n = Notification::make()->title('Retry result: '.Reasons::label($result->outcome).($result->reason ? ' ('.Reasons::label($result->reason).')' : ''));
                        ($result->outcome === ContactImport::OUTCOME_FAILED ? $n->danger() : $n->success())->send();
                    }),
            ])
            ->emptyStateHeading('No imports found');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageContactImports::route('/')];
    }
}
