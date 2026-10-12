<?php

namespace App\Modules\Crm\Filament\Resources\Customers\Pages;

use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Filament\Resources\Customers\CustomerResource;
use App\Modules\Crm\Models\CrmCustomer;
use App\Modules\Crm\Support\CrmAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Customer profile: details, every Case with its stage, purchases, history (CRM-CUS-002, 003). */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected string $view = 'crm::filament.customer';

    public function getTitle(): string
    {
        return $this->getRecord()->displayName();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('merge')->label('Merge a duplicate into this customer')->icon('heroicon-o-arrows-pointing-in')->color('gray')
                ->visible(fn () => CrmAccess::isAdmin() || CrmAccess::isTeamLead())
                ->schema([Select::make('from')->label('Duplicate customer (will be merged in)')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => CrmCustomer::whereNull('merged_into_id')->whereKeyNot($this->getRecord()->id)
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->limit(20)->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName().' · '.$c->email]))])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    CaseActions::mergeCustomers(CrmCustomer::findOrFail($data['from']), $this->getRecord(), auth()->user());
                    $this->record = $this->getRecord()->fresh();
                    Notification::make()->title('Customers merged')->success()->send();
                }),
        ];
    }
}
