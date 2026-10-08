<?php

namespace App\Modules\Mailer\Filament\Resources\Buyers\Pages;

use App\Modules\Mailer\Filament\Resources\Buyers\BuyerResource;
use App\Modules\Mailer\Models\Buyer;
use Filament\Resources\Pages\ViewRecord;

class ViewBuyer extends ViewRecord
{
    protected static string $resource = BuyerResource::class;

    protected string $view = 'mailer::filament.buyer';

    public function getTitle(): string
    {
        /** @var Buyer $buyer */
        $buyer = $this->record;

        return trim(($buyer->title ? $buyer->title.' ' : '').$buyer->fullName()) ?: $buyer->email;
    }
}
