<?php

namespace App\Filament\Admin\Resources\Suppliers\Pages;

use App\Filament\Admin\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Artisan;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reprice')
                ->label('Recalculate prices now')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->visible(fn () => ! $this->record->is_own_stock)
                ->modalDescription('Uses today\'s exchange rate and the saved price settings for every vehicle from this supplier (vehicles locked against supplier updates are skipped). Runs in the background within a few minutes.')
                ->schema([
                    Radio::make('margin')
                        ->label('Profit margin')
                        ->options([
                            'keep' => 'Keep the margin each vehicle was imported with',
                            'current' => 'Apply the current margin to all vehicles',
                        ])
                        ->descriptions([
                            'current' => 'Uses this supplier\'s margin, or the site default when it is empty.',
                        ])
                        ->default('keep')
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var Supplier $supplier */
                    $supplier = $this->record;
                    Artisan::queue('suppliers:reprice', ['supplier' => $supplier->slug, '--remargin' => ($data['margin'] ?? 'keep') === 'current']);
                    Notification::make()->title('Price recalculation queued')->success()->send();
                }),
        ];
    }
}
