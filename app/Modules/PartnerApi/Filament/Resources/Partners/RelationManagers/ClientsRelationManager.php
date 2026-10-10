<?php

namespace App\Modules\PartnerApi\Filament\Resources\Partners\RelationManagers;

use App\Modules\PartnerApi\Models\Partner;
use App\Modules\PartnerApi\Models\PartnerClient;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Connection keys on a partner (UI brief 2.4, FR-PRT-02). A secret is shown
 * exactly once, in a modal that only closes once the admin ticks "I have
 * copied the key"; afterwards only its prefix is ever visible.
 */
class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Connection keys';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No connection keys yet')
            ->emptyStateDescription('Create a key and send it to the partner securely, for example by phone or a password manager, never by plain email.')
            ->columns([
                TextColumn::make('label')->weight('bold'),
                TextColumn::make('environment')->badge()
                    ->formatStateUsing(fn (string $state) => $state === PartnerClient::ENV_LIVE ? 'Live' : 'Testing')
                    ->color(fn (string $state) => $state === PartnerClient::ENV_LIVE ? 'success' : 'gray'),
                TextColumn::make('client_id')->label('Client ID')->copyable()->fontFamily('mono'),
                TextColumn::make('key_prefix')->label('Secret')->fontFamily('mono')
                    ->formatStateUsing(fn (string $state) => $state.'…'),
                TextColumn::make('created_at')->label('Created')->date('j M Y'),
                TextColumn::make('last_used_at')->label('Last used')->since()->placeholder('Never'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === PartnerClient::STATUS_ACTIVE ? 'success' : 'danger'),
            ])
            ->headerActions([
                Action::make('createKey')
                    ->label('Create key')->icon('heroicon-o-key')
                    ->visible(fn () => $this->getOwnerRecord()->status !== Partner::STATUS_REVOKED)
                    ->schema([
                        TextInput::make('label')->label('Label')->required()->maxLength(80)->default('Production')
                            ->helperText('So you can tell keys apart, for example "Production" or "Website test".'),
                        Radio::make('environment')->label('Use')->required()->default(PartnerClient::ENV_LIVE)
                            ->options([PartnerClient::ENV_LIVE => 'Live', PartnerClient::ENV_TEST => 'Testing']),
                    ])
                    ->action(function (array $data) {
                        [$client, $secret] = PartnerClient::issue($this->getOwnerRecord(), $data['label'], $data['environment']);
                        $this->replaceMountedAction('showSecret', ['client_id' => $client->client_id, 'secret' => $secret]);
                    }),
            ])
            ->recordActions([
                Action::make('rotate')
                    ->label('Rotate')->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (PartnerClient $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Rotate this key?')
                    ->modalDescription('The old key keeps working for 7 days while the partner switches over.')
                    ->action(function (PartnerClient $record) {
                        $secret = $record->rotate();
                        $this->replaceMountedAction('showSecret', ['client_id' => $record->client_id, 'secret' => $secret]);
                    }),
                Action::make('revokeKey')
                    ->label('Revoke')->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn (PartnerClient $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Revoke this key now?')
                    ->modalDescription('It stops working immediately. The partner needs a new key to reconnect.')
                    ->action(function (PartnerClient $record) {
                        $record->revoke('Revoked by '.auth()->user()?->name);
                        Notification::make()->title('Key revoked.')->success()->send();
                    }),
            ]);
    }

    /** One-time reveal (FR-PRT-02). */
    public function showSecretAction(): Action
    {
        return Action::make('showSecret')
            ->modalHeading('Copy the new key now')
            ->modalDescription('Shown once. Store it now; we keep only a scrambled copy.')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCloseButton(false)
            ->modalCancelAction(false)
            ->modalSubmitActionLabel('Done')
            ->schema(fn (array $arguments) => [
                Text::make(new HtmlString(
                    '<div style="font-family:ui-monospace,monospace;font-size:13px;line-height:1.7">'
                    .'<div><strong>Client ID</strong><br>'.e($arguments['client_id'] ?? '').'</div>'
                    .'<div style="margin-top:8px"><strong>Client secret</strong><br><span style="word-break:break-all">'.e($arguments['secret'] ?? '').'</span></div>'
                    .'</div>'
                )),
                TextInput::make('copy_secret')->label('Secret (select and copy)')
                    ->default($arguments['secret'] ?? '')->readOnly()->copyable()->dehydrated(false),
                Checkbox::make('copied')->label('I have copied the key')->accepted()
                    ->validationMessages(['accepted' => 'Tick this once you have stored the key. It cannot be shown again.']),
            ])
            ->action(fn () => Notification::make()->title('Key saved. Only its first characters will show from now on.')->success()->send());
    }
}
