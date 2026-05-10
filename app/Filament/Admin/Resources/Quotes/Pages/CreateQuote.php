<?php

namespace App\Filament\Admin\Resources\Quotes\Pages;

use App\Filament\Admin\Resources\Quotes\QuoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;
}
