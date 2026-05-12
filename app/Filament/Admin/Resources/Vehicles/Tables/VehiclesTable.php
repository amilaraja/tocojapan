<?php

namespace App\Filament\Admin\Resources\Vehicles\Tables;

use App\Models\BodyType;
use App\Models\Make;
use App\Models\Vehicle;
use App\Services\Social\FacebookPosterService;
use App\Settings\SocialSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ref_no')->searchable()->sortable()->toggleable(),
                TextColumn::make('title')->searchable()->limit(40)->toggleable(),
                TextColumn::make('make.name')->label('Make')->sortable()->toggleable(),
                TextColumn::make('vehicleModel.name')->label('Model')->sortable()->toggleable(),
                TextColumn::make('year_first_reg')->label('Year')->numeric()->sortable()->toggleable(),
                TextColumn::make('mileage_km')->label('Mileage')->numeric()->sortable()->toggleable(),
                TextColumn::make('m3')->label('M³')->numeric(decimalPlaces: 3)->sortable()->toggleable(),
                TextColumn::make('price_fob')->label('FOB')->money(fn ($record) => $record->currency)->sortable()->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'sold' => 'danger',
                        'reserved' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('fb_shared_at')
                    ->label('FB')
                    ->badge()
                    ->state(fn (Vehicle $r) => $r->fb_shared_at ? 'Shared' : ($r->status === 'published' ? 'Not shared' : ''))
                    ->color(fn (Vehicle $r) => $r->fb_shared_at ? 'success' : 'gray')
                    ->icon(fn (Vehicle $r) => $r->fb_shared_at ? 'heroicon-o-check-circle' : null)
                    ->url(fn (Vehicle $r) => $r->fb_post_url)
                    ->openUrlInNewTab()
                    ->tooltip(fn (Vehicle $r) => $r->fb_shared_at?->format('Y-m-d H:i'))
                    ->toggleable(),
                TextColumn::make('published_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('price_on_request')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'sold' => 'Sold',
                    'reserved' => 'Reserved',
                ]),
                SelectFilter::make('make_id')
                    ->label('Make')
                    ->options(fn () => Make::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('body_type_id')
                    ->label('Body type')
                    ->options(fn () => BodyType::orderBy('name')->pluck('name', 'id')->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('shareToFacebook')
                    ->label('Share to FB')
                    ->icon('heroicon-o-share')
                    ->color('info')
                    ->modalHeading('Share to Facebook?')
                    ->modalDescription(fn (Vehicle $r) => 'This will post "'.$r->title.'" to your Facebook page now.')
                    ->modalSubmitActionLabel('Share now')
                    ->modalCancelActionLabel('Skip')
                    ->visible(fn (Vehicle $r) => $r->status === 'published'
                        && ! $r->fb_shared_at
                        && app(SocialSettings::class)->facebook_enabled)
                    ->action(function (Vehicle $r) {
                        $result = app(FacebookPosterService::class)->shareVehicle($r);

                        if ($result['success']) {
                            $r->forceFill([
                                'fb_shared_at' => now(),
                                'fb_post_id' => $result['post_id'] ?? null,
                                'fb_post_url' => $result['post_url'] ?? null,
                            ])->save();

                            Notification::make()
                                ->title('Shared to Facebook')
                                ->body($result['post_url'] ?? '')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Facebook share failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                Action::make('markSold')
                    ->label('Mark as sold')
                    ->icon('heroicon-o-banknotes')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Mark this vehicle as sold?')
                    ->modalDescription('It will show a SOLD badge on the public site for 90 days and then auto-hide. Buy and quote actions are removed immediately.')
                    ->visible(fn (Vehicle $r) => $r->status !== 'sold')
                    ->action(function (Vehicle $r) {
                        $r->forceFill(['status' => 'sold', 'sold_at' => now()])->save();
                        Notification::make()->title('Marked as sold.')->success()->send();
                    }),
                Action::make('unmarkSold')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Restore this vehicle to published?')
                    ->visible(fn (Vehicle $r) => $r->status === 'sold')
                    ->action(function (Vehicle $r) {
                        $r->forceFill(['status' => 'published', 'sold_at' => null])->save();
                        Notification::make()->title('Restored to published.')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $r) {
                                $r->update(['status' => 'published', 'published_at' => $r->published_at ?? now()]);
                                $count++;
                            }
                            Notification::make()->title("Published {$count} vehicle(s).")->success()->send();
                        }),
                    BulkAction::make('unpublish')
                        ->label('Move to draft')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            $records->toQuery()->update(['status' => 'draft']);
                            Notification::make()->title("Moved {$count} vehicle(s) to draft.")->success()->send();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
