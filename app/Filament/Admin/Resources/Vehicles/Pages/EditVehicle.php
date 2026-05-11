<?php

namespace App\Filament\Admin\Resources\Vehicles\Pages;

use App\Filament\Admin\Resources\Vehicles\VehicleResource;
use App\Services\Social\FacebookPosterService;
use App\Settings\SocialSettings;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected bool $promptFbShare = false;

    protected function getHeaderActions(): array
    {
        return [
            $this->shareToFacebookAction(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** Detect a transition into published BEFORE the record is overwritten. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $wasPublished = $this->record->status === 'published';
        $willBePublished = ($data['status'] ?? null) === 'published';

        $this->promptFbShare = ! $wasPublished && $willBePublished;

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->promptFbShare) {
            return;
        }

        $settings = app(SocialSettings::class);
        if (! $settings->facebook_enabled) {
            return;
        }

        if ($this->record->fb_shared_at) {
            return;
        }

        $this->mountAction('shareToFacebook');
    }

    public function shareToFacebookAction(): Action
    {
        return Action::make('shareToFacebook')
            ->label('Share to Facebook')
            ->icon('heroicon-o-share')
            ->color('info')
            ->modalHeading('Share to Facebook?')
            ->modalDescription(fn () => 'This will post "'.$this->record->title.'" to your Facebook page now.')
            ->modalSubmitActionLabel('Share now')
            ->modalCancelActionLabel('Skip')
            ->visible(fn () => app(SocialSettings::class)->facebook_enabled && ! $this->record->fb_shared_at)
            ->action(function () {
                $result = app(FacebookPosterService::class)->shareVehicle($this->record);

                if ($result['success']) {
                    $this->record->forceFill([
                        'fb_shared_at' => now(),
                        'fb_post_id' => $result['post_id'] ?? null,
                        'fb_post_url' => $result['post_url'] ?? null,
                    ])->save();

                    Notification::make()
                        ->title('Shared to Facebook')
                        ->body($result['post_url'] ?? '')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Facebook share failed')
                        ->body($result['error'] ?? 'Unknown error')
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
