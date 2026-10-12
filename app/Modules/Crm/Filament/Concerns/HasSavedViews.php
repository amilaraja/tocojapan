<?php

namespace App\Modules\Crm\Filament\Concerns;

use App\Modules\Crm\Models\CrmSavedView;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Personal saved views on CRM lists (CRM-DSH-006). Filament keeps filters,
 * search and sort in the URL, so a view is that query, reopened on click.
 */
trait HasSavedViews
{
    abstract protected function savedViewList(): string;

    /** @return list<Action|ActionGroup> */
    protected function savedViewActions(): array
    {
        $views = CrmSavedView::where('user_id', auth()->id())->where('list', $this->savedViewList())->orderBy('name')->get();

        $open = $views->map(fn (CrmSavedView $view) => Action::make('view_'.$view->id)
            ->label($view->name)
            ->icon('heroicon-o-funnel')
            ->url(static::getResource()::getUrl('index').'?'.http_build_query((array) $view->filters)))->all();

        $delete = $views->isEmpty() ? [] : [Action::make('deleteView')->label('Delete a saved view')->icon('heroicon-o-trash')->color('danger')
            ->schema([\Filament\Forms\Components\Select::make('view')->options($views->pluck('name', 'id'))->required()])
            ->action(function (array $data) {
                CrmSavedView::where('user_id', auth()->id())->whereKey($data['view'])->delete();
                Notification::make()->title('View deleted')->success()->send();
            })];

        return [
            ActionGroup::make([
                ...$open,
                Action::make('saveView')->label('Save this view')->icon('heroicon-o-bookmark')
                    ->schema([TextInput::make('name')->label('Name')->placeholder('My stage 02 Cases')->required()->maxLength(80)])
                    ->action(function (array $data) {
                        CrmSavedView::create([
                            'user_id' => auth()->id(),
                            'list' => $this->savedViewList(),
                            'name' => $data['name'],
                            'filters' => array_filter([
                                'filters' => $this->tableFilters,
                                'search' => $this->tableSearch ?: null,
                                'sort' => $this->tableSort ?? null,
                            ], fn ($v) => $v !== null && $v !== []),
                        ]);
                        Notification::make()->title('View saved')->body('Find it under "Views".')->success()->send();
                    }),
                ...$delete,
            ])->label('Views')->icon('heroicon-o-bookmark-square')->button()->color('gray'),
        ];
    }
}
