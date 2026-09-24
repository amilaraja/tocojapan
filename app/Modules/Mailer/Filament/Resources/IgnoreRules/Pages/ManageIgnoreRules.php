<?php

namespace App\Modules\Mailer\Filament\Resources\IgnoreRules\Pages;

use App\Modules\Mailer\Filament\Resources\IgnoreRules\IgnoreRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIgnoreRules extends ManageRecords
{
    protected static string $resource = IgnoreRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add to ignore list')
                ->mutateDataUsing(fn (array $data) => [...$data, 'created_by' => auth()->id()]),
        ];
    }
}
