<?php

namespace App\Modules\Mailer\Filament\Resources\Buyers\Pages;

use App\Modules\Mailer\Domain\Buyers\BuyerBackfill;
use App\Modules\Mailer\Domain\Buyers\BuyerExport;
use App\Modules\Mailer\Filament\Resources\Buyers\BuyerResource;
use App\Modules\Mailer\Jobs\RunBuyerBackfill;
use App\Modules\Mailer\Support\MailerAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBuyers extends ListRecords
{
    protected static string $resource = BuyerResource::class;

    public function getSubheading(): ?string
    {
        $s = app(BuyerBackfill::class)->status();
        $left = $s['messages_left'] > 0 ? ' · '.number_format($s['messages_left']).' older enquiry emails not read yet' : '';
        $brevo = $s['brevo_left'] > 0 ? ' · '.number_format($s['brevo_left']).' buyers waiting to be filled into Brevo' : '';

        return number_format($s['buyers']).' buyers from '.number_format($s['messages_total']).' enquiry emails'.$left.$brevo.'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')
                ->label('Download CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => BuyerExport::download($this->getFilteredSortedTableQuery(), 'toco-buyers')),
            Action::make('backfill')
                ->label('Read older enquiries')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn () => MailerAccess::isAdmin())
                ->requiresConfirmation()
                ->modalDescription('Reads buyer details from enquiry emails imported before this feature (the mailbox is only read, nothing is changed in it), then fills empty details into Brevo. Runs in the background; this page shows the progress.')
                ->action(function () {
                    RunBuyerBackfill::dispatch();
                    Notification::make()->title('Started. Progress shows at the top of this page.')->success()->send();
                }),
        ];
    }
}
