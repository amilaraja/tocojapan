<?php

namespace App\Filament\Admin\Resources\Vehicles\Pages;

use App\Filament\Admin\Resources\Vehicles\VehicleResource;
use App\Support\Csv;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $query = $this->getFilteredTableQuery()->with(['make', 'vehicleModel', 'bodyType']);

                    return Csv::download(
                        'vehicles-'.now()->format('Ymd-His').'.csv',
                        ['ref_no', 'title', 'make', 'model', 'body_type', 'year', 'mileage_km', 'engine_cc', 'transmission', 'm3', 'price_fob', 'price_fob_discount', 'effective_price', 'currency', 'status', 'published_at'],
                        $query->lazy()->map(fn ($v) => [
                            $v->ref_no,
                            $v->title,
                            $v->make?->name,
                            $v->vehicleModel?->name,
                            $v->bodyType?->name,
                            $v->year_first_reg,
                            $v->mileage_km,
                            $v->engine_cc,
                            $v->transmission,
                            $v->m3,
                            $v->price_fob,
                            $v->price_fob_discount,
                            $v->effectivePriceFob(),
                            $v->currency,
                            $v->status,
                            $v->published_at?->toDateString(),
                        ])
                    );
                }),
        ];
    }
}
