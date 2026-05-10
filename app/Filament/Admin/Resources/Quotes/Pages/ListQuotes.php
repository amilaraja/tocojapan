<?php

namespace App\Filament\Admin\Resources\Quotes\Pages;

use App\Filament\Admin\Resources\Quotes\QuoteResource;
use App\Support\Csv;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $query = $this->getFilteredTableQuery()->with(['user', 'vehicle', 'country', 'port']);

                    return Csv::download(
                        'quotes-'.now()->format('Ymd-His').'.csv',
                        ['reference', 'customer_name', 'customer_email', 'vehicle_ref', 'vehicle_title', 'country', 'port', 'status', 'price_quoted', 'cif_total', 'currency', 'valid_until', 'created_at'],
                        $query->lazy()->map(fn ($q) => [
                            $q->reference,
                            $q->user?->name,
                            $q->user?->email,
                            $q->vehicle?->ref_no,
                            $q->vehicle?->title,
                            $q->country?->name,
                            $q->port?->name,
                            $q->status,
                            $q->price_quoted,
                            $q->cif_total,
                            $q->currency,
                            $q->valid_until?->toDateString(),
                            $q->created_at?->toIso8601String(),
                        ])
                    );
                }),
        ];
    }
}
