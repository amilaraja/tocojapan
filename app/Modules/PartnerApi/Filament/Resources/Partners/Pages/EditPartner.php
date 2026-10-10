<?php

namespace App\Modules\PartnerApi\Filament\Resources\Partners\Pages;

use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerResource;
use App\Modules\PartnerApi\Domain\Webhooks\WebhookSender;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPartner extends EditRecord
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test event')->icon('heroicon-o-paper-airplane')->color('gray')
                ->visible(fn () => filled($this->record->webhook_url))
                ->requiresConfirmation()
                ->modalHeading('Send a test notification?')
                ->modalDescription(fn () => 'A signed test message goes to '.$this->record->webhook_url.' and you see the answer here.')
                ->action(function () {
                    [$ok, $code, $error] = app(WebhookSender::class)->ping($this->record);
                    $ok
                        ? Notification::make()->title('Delivered')->body('The partner answered '.$code.'.')->success()->send()
                        : Notification::make()->title('Not delivered')->body($error ?? 'No answer')->danger()->persistent()->send();
                }),
            Action::make('signingSecret')
                ->label('Notification secret')->icon('heroicon-o-finger-print')->color('gray')
                ->visible(fn () => filled($this->record->webhook_url))
                ->modalHeading('Notification signing secret')
                ->modalDescription('Give this to the partner so their system can check that notifications really come from Toco. Send it securely, never by plain email.')
                ->schema(fn () => [
                    TextInput::make('secret')->label('Secret')->default($this->record->ensureWebhookSecret())->readOnly()->copyable()->dehydrated(false),
                ])
                ->modalSubmitActionLabel('Make a new secret')
                ->modalCancelActionLabel('Close')
                ->requiresConfirmation(false)
                ->action(function () {
                    $this->record->rotateWebhookSecret();
                    Notification::make()->title('New secret made. Send it to the partner; the old one no longer signs messages.')->success()->send();
                }),
            PartnerResource::suspendAction(),
            PartnerResource::reactivateAction(),
            PartnerResource::revokeAction(),
        ];
    }
}
