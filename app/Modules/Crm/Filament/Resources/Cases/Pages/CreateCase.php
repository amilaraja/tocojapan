<?php

namespace App\Modules\Crm\Filament\Resources\Cases\Pages;

use App\Models\Country;
use App\Models\Port;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\CustomerMatcher;
use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
use App\Modules\Crm\Models\CrmCustomer;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmUi;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Phone, WhatsApp, email or walk-in enquiries entered by staff (CRM-CAS-003). */
class CreateCase extends CreateRecord
{
    protected static string $resource = CaseResource::class;

    protected static ?string $title = 'New Case';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Customer')->schema([
                Select::make('customer_id')->label('Existing customer')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => CrmCustomer::whereNull('merged_into_id')
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->limit(20)->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName().($c->email ? " · {$c->email}" : '')]))
                    ->getOptionLabelUsing(fn ($value) => CrmCustomer::find($value)?->displayName())
                    ->live()
                    ->helperText('Or leave empty and enter a new customer below.'),
                Grid::make(2)->visible(fn (Get $get) => blank($get('customer_id')))->schema([
                    TextInput::make('name')->required(fn (Get $get) => blank($get('customer_id')))->maxLength(190),
                    TextInput::make('email')->email()->maxLength(190),
                    TextInput::make('phone')->tel()->maxLength(60),
                    TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(60),
                    Select::make('country_id')->label('Country')->searchable()->options(fn () => Country::orderBy('name')->pluck('name', 'id')->all()),
                ]),
            ]),
            Section::make('Enquiry')->columns(2)->schema([
                Select::make('type')->options(Crm::TYPES)->default(Crm::TYPE_VEHICLE)->required()->live(),
                Select::make('source')->options(collect(Crm::SOURCES)->only(Crm::MANUAL_SOURCES)->all())->default('whatsapp')->required(),
                Select::make('vehicle_id')->label('Vehicle')->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Vehicle::where('stock_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                        ->limit(20)->get()->mapWithKeys(fn ($v) => [$v->id => $v->stock_no.' · '.trim($v->title)]))
                    ->getOptionLabelUsing(fn ($value) => ($v = Vehicle::find($value)) ? $v->stock_no.' · '.trim($v->title) : null),
                Select::make('dest_port_id')->label('Destination port')->searchable()
                    ->options(fn () => Port::with('country')->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' ('.$p->country?->name.')'])->all()),
                TextInput::make('subject')->maxLength(255)->columnSpanFull(),
                Textarea::make('note')->label('What did the customer ask?')->rows(4)->required()->columnSpanFull(),
                Select::make('owner_id')->label('Owner')->options(fn () => CrmUi::staffOptions())
                    ->default(fn () => auth()->id())->helperText('Leave empty to share it out by rotation.'),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $customer = filled($data['customer_id'] ?? null)
            ? CrmCustomer::findOrFail($data['customer_id'])
            : CustomerMatcher::match(['email' => $data['email'] ?? null, 'name' => $data['name'] ?? null, 'phone' => $data['phone'] ?? null, 'country_id' => $data['country_id'] ?? null]);
        if (blank($data['customer_id'] ?? null) && filled($data['whatsapp'] ?? null) && blank($customer->whatsapp)) {
            $customer->forceFill(['whatsapp' => $data['whatsapp']])->save();
        }
        if ($data['type'] === Crm::TYPE_VEHICLE && blank($data['vehicle_id'] ?? null) && blank($data['subject'] ?? null)) {
            throw ValidationException::withMessages(['data.vehicle_id' => 'Choose the vehicle, or describe the request in Subject.']);
        }

        $port = filled($data['dest_port_id'] ?? null) ? Port::find($data['dest_port_id']) : null;
        $owner = filled($data['owner_id'] ?? null) ? User::find($data['owner_id']) : null;

        return CaseActions::open($customer, array_filter([
            'type' => $data['type'],
            'source' => $data['source'],
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'dest_port_id' => $port?->id,
            'dest_country_id' => $port?->country_id ?? $customer->country_id,
            'subject' => $data['subject'] ?? null,
        ]), [
            'message' => $data['note'],
            'created_by' => auth()->user(),
            'owner' => $owner && CrmAccess::canUse($owner) ? $owner : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return CaseResource::getUrl('view', ['record' => $this->record]);
    }
}
