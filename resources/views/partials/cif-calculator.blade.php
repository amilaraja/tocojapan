{{-- CIF calculator body (country/port pickers, import rules, add-ons, total).
     Needs an enclosing x-data="cifCalc({...})" scope — see
     partials/cif-calc-script. Shared by the detail page and the quick view.
     Params: $vehicle, $proformaEligible. --}}
<p class="text-[13px] uppercase tracking-wider text-toco-red font-bold">Calculate Your Total Price</p>
<h3 class="font-bold text-toco-navy text-base mb-3">Estimate landed cost</h3>

<div class="space-y-2 text-sm">
    <select x-model="countryId" @change="onCountry()" :disabled="countriesLoading" class="w-full border-line rounded-sm disabled:bg-toco-silver-2">
        <option value="" x-text="countriesLoading ? 'Loading countries…' : '— Country —'">— Country —</option>
        <template x-for="c in countries" :key="c.id">
            <option :value="c.id" x-text="c.name + ' (' + c.iso2 + ')'"></option>
        </template>
    </select>
    <select x-model="portId" @change="onPort()" :disabled="!ports.length" class="w-full border-line rounded-sm disabled:bg-toco-silver-2">
        <option value="">— Port —</option>
        <template x-for="p in ports" :key="p.id">
            <option :value="p.id" x-text="p.name"></option>
        </template>
    </select>
    <button type="button" x-show="!auto" @click="submit()" :disabled="loading || !portId" class="w-full bg-toco-navy hover:bg-toco-navy-deep disabled:opacity-50 text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">
        <span x-show="!loading">Calculate</span>
        <span x-show="loading" x-cloak>Calculating…</span>
    </button>
</div>

{{-- Import regulations for the chosen port --}}
<div x-show="regulation()" x-cloak class="mt-3 rounded-sm p-3 leading-snug" style="background:#FFF4E5;border:1px solid #F5C58A;border-left:4px solid #EF7C0E;font-size:14px">
    <p class="font-bold mb-1.5" style="font-size:15px;color:#9A3412">Import rules — your port</p>
    <p x-show="regulation()?.description" x-text="regulation()?.description" class="text-ink mb-2"></p>
    <dl class="space-y-1">
        <div x-show="regulation()?.age_limit" class="flex gap-2"><dt class="w-28 shrink-0 font-semibold" style="color:#9A3412">Age limit</dt><dd class="font-bold text-toco-navy" x-text="regulation()?.age_limit"></dd></div>
        <div x-show="regulation()?.shipment_time" class="flex gap-2"><dt class="w-28 shrink-0 font-semibold" style="color:#9A3412">Shipment time</dt><dd class="font-bold text-toco-navy" x-text="regulation()?.shipment_time"></dd></div>
        <div x-show="regulation()?.notes" class="flex gap-2"><dt class="w-28 shrink-0 font-semibold" style="color:#9A3412">Notes</dt><dd class="text-ink whitespace-pre-line" x-text="regulation()?.notes"></dd></div>
    </dl>
</div>

@if ($proformaEligible)
    {{-- LC destinations (Import regulations → Payment modes) get a proforma invoice. --}}
    <div x-show="regulation()?.lc" x-cloak class="mt-3 border border-toco-navy/30 rounded-sm p-3 text-[12px] leading-snug">
        <p class="text-[13px] uppercase tracking-wider text-toco-navy font-bold mb-1">LC payment accepted</p>
        <p class="text-ink-soft mb-2">Paying by Letter of Credit? Download a proforma invoice for this vehicle to open your LC.</p>
        @auth
            <a :href="'{{ route('proforma.create', $vehicle->slug) }}?port_id=' + portId" class="block text-center bg-toco-navy hover:bg-toco-navy-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">Get a proforma invoice</a>
        @else
            <a href="{{ route('login') }}" class="block text-center bg-toco-navy hover:bg-toco-navy-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">Sign in to get a proforma invoice</a>
        @endauth
    </div>
@endif

<div x-show="error" x-cloak class="text-toco-red text-[12px] mt-3" x-text="error"></div>

{{-- Add-on rows + total — visible once CIF result is in --}}
<template x-if="result">
    <div class="mt-4 pt-4 border-t border-line space-y-2 text-sm">
        <label class="flex items-center justify-between gap-2 cursor-pointer">
            <span class="inline-flex items-center gap-2">
                <input type="checkbox" x-model="marine" class="text-toco-red">
                <span class="font-semibold text-ink">Marine Insurance</span>
                <span class="text-ink-soft text-[11px]" title="Flat per-shipment marine insurance fee.">?</span>
            </span>
            <span class="font-semibold tabular-nums" x-text="fmt(marineInsuranceFee)"></span>
        </label>
        <label class="flex items-center justify-between gap-2 cursor-pointer">
            <span class="inline-flex items-center gap-2">
                <input type="checkbox" x-model="maintenance" class="text-toco-red">
                <span class="font-semibold text-ink">Maintenance Package</span>
            </span>
            <span class="font-semibold tabular-nums" x-text="fmt(maintenanceFee)"></span>
        </label>
        <label class="flex items-center justify-between gap-2"
            :class="preInspectionMandatory() ? 'cursor-not-allowed' : 'cursor-pointer'">
            <span class="inline-flex items-center gap-2">
                <input type="checkbox" x-model="preInspection"
                    :disabled="preInspectionMandatory()"
                    class="text-toco-red disabled:opacity-60 disabled:cursor-not-allowed">
                <span class="font-semibold text-ink">Pre-inspection Fee</span>
                <span x-show="preInspectionMandatory()" x-cloak
                    class="text-[10px] text-toco-red uppercase tracking-widest font-bold ml-1">required</span>
            </span>
            <span class="font-semibold tabular-nums" x-text="fmt(preInspectionFee)"></span>
        </label>

        {{-- Options accordion --}}
        <div x-data="{ open: false }" class="border border-line rounded-sm mt-2">
            <button type="button" @click="open = !open" class="w-full px-3 py-2.5 flex items-center justify-between bg-toco-silver-2 text-toco-navy font-bold text-[12px] uppercase tracking-widest">
                <span>Option (Enhance your drive)</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" :class="open ? 'rotate-180' : ''" class="transition-transform"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <ul x-show="open" x-cloak class="divide-y divide-line">
                <template x-for="opt in options" :key="opt.id">
                    <li>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer hover:bg-toco-silver-2/50">
                            <span class="inline-flex items-center gap-2 text-[13px]">
                                <input type="checkbox" :value="opt.id" x-model="selectedOptions" class="text-toco-red">
                                <span class="font-semibold text-ink" x-text="opt.name"></span>
                                <span x-show="opt.tooltip" :title="opt.tooltip" class="text-ink-soft text-[11px]">?</span>
                            </span>
                            <span class="font-semibold tabular-nums text-[13px]"
                                  :class="opt.price === null ? 'text-toco-red uppercase tracking-widest text-[11px]' : ''"
                                  x-text="opt.price === null ? 'ASK' : fmt(opt.price)"></span>
                        </label>
                    </li>
                </template>
            </ul>
            <p x-show="open && hasAskSelected()" x-cloak class="px-3 py-2 text-[11px] text-ink-soft italic border-t border-line">
                * ASK options will be quoted separately by our sales team — they don't affect the total below.
            </p>
        </div>

        <dl class="border-t border-line pt-2 mt-2 space-y-1">
            <div class="flex justify-between"><dt class="text-ink-soft text-[12px]">Car Price (FOB)</dt><dd class="tabular-nums" x-text="fmt(result.price_fob)"></dd></div>
            <div class="flex justify-between"><dt class="text-ink-soft text-[12px]">Freight to <span x-text="result.port?.name"></span></dt><dd class="tabular-nums" x-text="fmt(result.freight)"></dd></div>
            <div class="flex justify-between text-base font-bold border-t border-line pt-2 mt-1"><dt class="text-toco-navy">Total Price</dt><dd class="font-extrabold text-toco-red tabular-nums" x-text="fmt(grandTotal())"></dd></div>
        </dl>
        <p class="text-[10px] text-ink-soft leading-snug">Total = Car + Shipping + the add-ons you ticked + priced options. Land charges in destination country not included.</p>
    </div>
</template>
