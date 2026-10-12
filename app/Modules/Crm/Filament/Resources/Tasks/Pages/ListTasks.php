<?php

namespace App\Modules\Crm\Filament\Resources\Tasks\Pages;

use App\Modules\Crm\Filament\Concerns\HasSavedViews;
use App\Modules\Crm\Filament\Resources\Tasks\TaskResource;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    use HasSavedViews;

    protected static string $resource = TaskResource::class;

    protected function savedViewList(): string
    {
        return 'tasks';
    }

    protected function getHeaderActions(): array
    {
        return $this->savedViewActions();
    }
}
