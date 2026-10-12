@php
    $crmCustomer = \App\Modules\Crm\Domain\CustomerMatcher::forUser(auth()->user());
    $crmCase = $crmCustomer
        ? \App\Modules\Crm\Models\CrmCase::active()->where('customer_id', $crmCustomer->id)->where('vehicle_id', $vehicle->id)->latest('id')->first()
        : null;
@endphp
@if ($crmCase)
    <a href="{{ $crmCase->portalUrl() }}" class="inline-flex items-center gap-2 mt-2 bg-toco-navy text-white text-sm font-semibold px-3 py-1.5 rounded-sm hover:bg-toco-red">
        Your enquiry: Stage {{ sprintf('%02d', $crmCase->stage) }} {{ \App\Modules\Crm\Domain\Crm::STAGES[$crmCase->stage] ?? '' }} →
    </a>
@endif
