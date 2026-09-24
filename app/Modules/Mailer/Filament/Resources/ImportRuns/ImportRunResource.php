<?php

namespace App\Modules\Mailer\Filament\Resources\ImportRuns;

use App\Modules\Mailer\Domain\Importer\Reasons;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Models\ImportRun;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** S12 Run log (TOC-LOG-001). Read-only. */
class ImportRunResource extends Resource
{
    protected static ?string $model = ImportRun::class;

    protected static ?string $cluster = Importer::class;

    protected static ?string $slug = 'run-log';

    protected static ?string $navigationLabel = 'Run log';

    protected static ?string $modelLabel = 'run';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('Started')->dateTime('j M Y, H:i:s')->sortable(),
                TextColumn::make('trigger')->label('Started by')->badge()
                    ->formatStateUsing(fn (string $state) => ['schedule' => 'Schedule', 'manual' => 'Run now', 'backfill' => 'Backfill'][$state] ?? $state),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ['success' => 'OK', 'failed' => 'Failed', 'running' => 'Running', 'skipped' => 'Skipped'][$state] ?? $state)
                    ->color(fn (string $state) => ['success' => 'success', 'failed' => 'danger', 'running' => 'info'][$state] ?? 'gray'),
                TextColumn::make('scanned')->label('Messages'),
                TextColumn::make('created')->label('Added'),
                TextColumn::make('updated')->label('Updated'),
                TextColumn::make('skipped')->label('Skipped')
                    ->state(fn (ImportRun $r) => collect($r->skipped ?? [])->map(fn ($n, $reason) => Reasons::label($reason).': '.$n)->values()->all())
                    ->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('failed')->label('Failed')->color(fn ($state) => $state > 0 ? 'danger' : null),
                TextColumn::make('finished_at')->label('Took')
                    ->state(fn (ImportRun $r) => $r->finished_at ? $r->started_at->diffInSeconds($r->finished_at, true).' s' : '—'),
                TextColumn::make('error')->label('Problem')->wrap()->placeholder('—')->color('danger'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['success' => 'OK', 'failed' => 'Failed', 'skipped' => 'Skipped', 'running' => 'Running']),
                SelectFilter::make('trigger')->label('Started by')->options(['schedule' => 'Schedule', 'manual' => 'Run now', 'backfill' => 'Backfill']),
            ])
            ->emptyStateHeading('No runs yet');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageImportRuns::route('/')];
    }
}
