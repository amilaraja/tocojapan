<?php

namespace App\Modules\Crm\Filament\Resources\Cases\Pages;

use App\Modules\Crm\Filament\Concerns\HasSavedViews;
use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCases extends ListRecords
{
    use HasSavedViews;

    protected static string $resource = CaseResource::class;

    protected function savedViewList(): string
    {
        return 'cases';
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->savedViewActions(),
            CreateAction::make()->label('New Case'),
        ];
    }
}
