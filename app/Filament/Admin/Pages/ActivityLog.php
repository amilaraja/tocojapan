<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.pages.activity-log';

    protected static ?string $title = 'Activity log';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?int $navigationSort = 95;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with('causer', 'subject')->latest()->limit(500))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('causer.name')->label('Who')->searchable(),
                TextColumn::make('description')->label('Action')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')->label('Subject')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                TextColumn::make('subject_id')->label('ID')->toggleable(),
                TextColumn::make('properties')->label('Changes')
                    ->formatStateUsing(function ($state) {
                        $arr = is_array($state) ? $state : json_decode((string) $state, true);
                        if (! is_array($arr)) {
                            return '';
                        }
                        $attrs = $arr['attributes'] ?? [];
                        $old = $arr['old'] ?? [];
                        $diffs = [];
                        foreach ($attrs as $k => $v) {
                            $oldV = $old[$k] ?? null;
                            $diffs[] = $k.': '.json_encode($oldV).' → '.json_encode($v);
                        }

                        return implode(' · ', $diffs);
                    })
                    ->wrap()
                    ->limit(140)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('description')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                ]),
                SelectFilter::make('subject_type')
                    ->label('Subject type')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($v, $k) => [$k => class_basename($v)])
                        ->all()),
            ]);
    }
}
