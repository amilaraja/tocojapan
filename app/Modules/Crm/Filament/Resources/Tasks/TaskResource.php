<?php

namespace App\Modules\Crm\Filament\Resources\Tasks;

use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
use App\Modules\Crm\Filament\Resources\Tasks\Pages\ListTasks;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmUi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Admin → CRM → Tasks (CRM-ACT-004). */
class TaskResource extends Resource
{
    protected static ?string $model = CrmTask::class;

    protected static ?string $slug = 'tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = CrmTask::whereNull('done_at')->where('assignee_id', auth()->id())->where('due_at', '<', now())->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return CrmAccess::isAdmin() ? $query : $query->where(fn ($q) => $q->where('assignee_id', auth()->id())
            ->orWhereIn('case_id', CrmAccess::scopeCases(CrmCase::query())->select('id')));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['case.customer', 'assignee']))
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('title')->weight('bold')->searchable()->description(fn (CrmTask $r) => $r->case?->reference.' · '.$r->case?->customer?->displayName()),
                TextColumn::make('due_at')->label('Due')->dateTime('D j M H:i', CrmUi::tz())->sortable()
                    ->color(fn (CrmTask $r) => $r->isOverdue() ? 'danger' : null),
                TextColumn::make('assignee.name')->label('For'),
                TextColumn::make('done_at')->label('Done')->since()->placeholder('Open'),
            ])
            ->filters([
                TernaryFilter::make('open')->label('Open tasks')->default(true)
                    ->queries(true: fn (Builder $query) => $query->whereNull('done_at'), false: fn (Builder $query) => $query->whereNotNull('done_at')),
                SelectFilter::make('assignee_id')->label('For')->options(fn () => CrmUi::staffOptions())->default(fn () => CrmAccess::isAdmin() ? null : auth()->id()),
            ])
            ->recordActions([
                Action::make('done')->label('Done')->icon('heroicon-m-check')->visible(fn (CrmTask $r) => $r->done_at === null)
                    ->action(fn (CrmTask $r) => CaseActions::completeTask($r, auth()->user())),
            ])
            ->recordUrl(fn (CrmTask $r) => $r->case ? CaseResource::getUrl('view', ['record' => $r->case]) : null);
    }

    public static function getPages(): array
    {
        return ['index' => ListTasks::route('/')];
    }
}
