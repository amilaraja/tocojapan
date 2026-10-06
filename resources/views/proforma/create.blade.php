@php
    $fob = (float) ($vehicle->price_fob ?? 0);
    $effective = (float) ($vehicle->effectivePriceFob() ?? 0);
    $insurance = (float) (app(\App\Settings\CifSettings::class)->marine_insurance_usd ?: 0);
    $photo = $vehicle->cardPhotoUrl();
    $address = old('consignee_address', trim(($user->address ?? '').($user->country ? "\n".$user->country->name : '')));
@endphp
<x-layouts.site :title="'LC proforma invoice — '.$vehicle->title">
    <section class="max-w-[1100px] mx-auto px-6 py-10">
        <div class="text-xs uppercase tracking-widest mb-2">
            <a href="{{ route('vehicles.show', $vehicle->slug) }}" class="text-ink-soft hover:text-toco-red">← Back to vehicle</a>
        </div>
        <h1 class="text-2xl font-extrabold text-toco-navy">LC proforma invoice</h1>
        <p class="text-sm text-ink-soft mt-1 mb-6">For buyers paying by Letter of Credit. Check the consignee details — they are printed on the invoice exactly as entered.</p>

        <div
            class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6"
            x-data="{
                countries: @js($destinations),
                countryId: '{{ old('country_id', $selectedCountryId) }}',
                portId: '{{ old('port_id', $selectedPortId) }}',
                get ports() { const c = this.countries.find(c => c.id == this.countryId); return c ? c.ports : []; },
                get port() { return this.ports.find(p => p.id == this.portId) || null; },
                get freight() { return this.port ? Math.round({{ (float) $vehicle->m3 }} * this.port.rate_per_m3 * 100) / 100 : null; },
                get total() { return this.freight === null ? null : {{ $effective }} + {{ $insurance }} + this.freight; },
                usd(v) { return v === null ? '—' : 'US$ ' + Math.round(v).toLocaleString('en-US'); },
            }"
        >
            <form method="POST" action="{{ route('proforma.store', $vehicle->slug) }}" class="space-y-6">
                @csrf
                <div class="bg-white border border-line rounded-sm p-5 space-y-3">
                    <h2 class="font-bold text-toco-navy">Port of destination</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Country *</label>
                            <select name="country_id" x-model="countryId" @change="portId = ''" class="w-full border-line rounded-sm" required>
                                <option value="">Select country</option>
                                <template x-for="c in countries" :key="c.id">
                                    <option :value="c.id" x-text="c.name" :selected="c.id == countryId"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Port *</label>
                            <select name="port_id" x-model="portId" :disabled="!countryId" class="w-full border-line rounded-sm disabled:bg-toco-silver-2" required>
                                <option value="">Select port</option>
                                <template x-for="p in ports" :key="p.id">
                                    <option :value="p.id" x-text="p.name" :selected="p.id == portId"></option>
                                </template>
                            </select>
                            @error('port_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="text-[12px] text-ink-soft">Only destinations that accept LC payment are listed.</p>
                </div>

                <div class="bg-white border border-line rounded-sm p-5 space-y-3">
                    <h2 class="font-bold text-toco-navy">Consignee details</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Name / company *</label>
                            <input type="text" name="consignee_name" value="{{ old('consignee_name', $user->name) }}" required maxlength="120" class="w-full border-line rounded-sm">
                            @error('consignee_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Full address *</label>
                            <textarea name="consignee_address" rows="3" required maxlength="500" class="w-full border-line rounded-sm" placeholder="Street, city, state, postcode, country">{{ $address }}</textarea>
                            @error('consignee_address')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Phone *</label>
                            <input type="text" name="consignee_phone" value="{{ old('consignee_phone', $user->phone) }}" required maxlength="40" class="w-full border-line rounded-sm" placeholder="+61 …">
                            @error('consignee_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Email *</label>
                            <input type="email" name="consignee_email" value="{{ old('consignee_email', $user->email) }}" required maxlength="190" class="w-full border-line rounded-sm">
                            @error('consignee_email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="!portId" class="w-full bg-toco-red hover:bg-toco-red-deep disabled:bg-toco-silver-2 disabled:cursor-not-allowed text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
                    Generate proforma invoice (PDF)
                </button>
            </form>

            <aside class="space-y-4 lg:sticky lg:top-20 self-start">
                <div class="bg-white border border-line rounded-sm overflow-hidden">
                    @if ($photo)<img src="{{ $photo }}" alt="{{ $vehicle->title }}" class="w-full aspect-[4/3] object-cover">@endif
                    <div class="p-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Stock #{{ $vehicle->stock_no }}</p>
                        <p class="font-extrabold text-toco-navy leading-tight mt-1">{{ $vehicle->title }}</p>
                        <dl class="mt-4 space-y-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-ink-soft">FOB price</dt><dd class="font-semibold">US$ {{ number_format($fob) }}</dd></div>
                            @if ($fob > $effective)
                                <div class="flex justify-between text-toco-red"><dt>Discount</dt><dd class="font-semibold">− US$ {{ number_format($fob - $effective) }}</dd></div>
                            @endif
                            <div class="flex justify-between"><dt class="text-ink-soft">Insurance</dt><dd class="font-semibold">US$ {{ number_format($insurance) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-ink-soft">Freight (<span>{{ number_format((float) $vehicle->m3, 2) }}</span> M3)</dt><dd class="font-semibold" x-text="usd(freight)"></dd></div>
                            <div class="flex justify-between border-t border-line pt-2 mt-2 text-toco-navy"><dt class="font-bold">Total CIF</dt><dd class="font-extrabold text-toco-red" x-text="usd(total)"></dd></div>
                        </dl>
                        <p class="text-[11px] text-ink-soft mt-3">Valid for {{ app(\App\Settings\ProformaSettings::class)->validity_days }} day(s) from today.</p>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.site>
