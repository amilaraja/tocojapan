<x-layouts.site :title="'Checkout — '.$vehicle->title">
    <section class="max-w-[1100px] mx-auto px-6 py-10">
        <div class="text-xs uppercase tracking-widest mb-2">
            <a href="{{ route('vehicles.show', $vehicle->slug) }}" class="text-ink-soft hover:text-toco-red">← Back to vehicle</a>
        </div>
        <h1 class="text-2xl font-extrabold text-toco-navy mb-6">Bank-transfer checkout</h1>

        @php
            $countryPayload = $countries->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'ports' => $c->ports->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'rate_per_m3' => (float) $p->rate_per_m3,
                ])->all(),
            ])->all();
        @endphp
        <div
            class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6"
            x-data="bankCheckout(@js($countryPayload), {{ (float) ($vehicle->effectivePriceFob() ?? $vehicle->price_fob) }}, {{ (float) ($vehicle->m3 ?: 0) }}, {{ (float) (app(\App\Settings\CifSettings::class)->marine_insurance_usd ?: 0) }}, {{ $destPort?->country_id ? (int) $destPort->country_id : 0 }}, {{ $destPort?->id ? (int) $destPort->id : 0 }})"
        >
            <form method="POST" action="{{ route('checkout.bank.store', $vehicle->slug) }}" class="space-y-6">
                @csrf

                {{-- Destination --}}
                <div class="bg-white border border-line rounded-sm p-5 space-y-3">
                    <h2 class="font-bold text-toco-navy">Destination</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Country *</label>
                            <select name="country_id" x-model="countryId" @change="onCountryChange" class="w-full border-line rounded-sm" required>
                                <option value="">Select country</option>
                                <template x-for="c in countries" :key="c.id">
                                    <option :value="c.id" x-text="c.name"></option>
                                </template>
                            </select>
                            @error('country_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Destination port *</label>
                            <select name="port_id" x-model="portId" :disabled="!countryId" class="w-full border-line rounded-sm disabled:bg-toco-silver-2" required>
                                <option value="">Select port</option>
                                <template x-for="p in availablePorts" :key="p.id">
                                    <option :value="p.id" x-text="p.name"></option>
                                </template>
                            </select>
                            @error('port_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Shipping address --}}
                <div class="bg-white border border-line rounded-sm p-5 space-y-3">
                    <h2 class="font-bold text-toco-navy">Shipping address</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Full name *</label>
                            <input type="text" name="ship_to_name" value="{{ old('ship_to_name', $user->name) }}" required class="w-full border-line rounded-sm">
                            @error('ship_to_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Phone *</label>
                            <input type="text" name="ship_to_phone" value="{{ old('ship_to_phone') }}" required class="w-full border-line rounded-sm" placeholder="+94 …">
                            @error('ship_to_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Address line 1 *</label>
                            <input type="text" name="ship_to_address_line1" value="{{ old('ship_to_address_line1') }}" required class="w-full border-line rounded-sm">
                            @error('ship_to_address_line1')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Address line 2</label>
                            <input type="text" name="ship_to_address_line2" value="{{ old('ship_to_address_line2') }}" class="w-full border-line rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">City *</label>
                            <input type="text" name="ship_to_city" value="{{ old('ship_to_city') }}" required class="w-full border-line rounded-sm">
                            @error('ship_to_city')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">State / Province</label>
                            <input type="text" name="ship_to_state" value="{{ old('ship_to_state') }}" class="w-full border-line rounded-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-widest text-ink-soft mb-1">Postcode</label>
                            <input type="text" name="ship_to_postcode" value="{{ old('ship_to_postcode') }}" class="w-full border-line rounded-sm">
                        </div>
                    </div>
                </div>

                {{-- Confirm + submit --}}
                <div class="bg-white border border-line rounded-sm p-5 space-y-3">
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="confirm" value="1" required class="mt-0.5 rounded">
                        <span>I confirm the destination port, address and amount above and want to place this order. After confirmation, bank transfer instructions will be shown.</span>
                    </label>
                    @error('confirm')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <button
                        type="submit"
                        :disabled="!portId"
                        class="w-full bg-toco-red hover:bg-toco-red-deep disabled:bg-toco-silver-2 disabled:cursor-not-allowed text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm"
                    >
                        Place order — <span x-text="formatUsd(cif.total)"></span>
                    </button>
                </div>
            </form>

            {{-- Summary --}}
            <aside class="space-y-4 lg:sticky lg:top-20 self-start">
                <div class="bg-white border border-line rounded-sm overflow-hidden">
                    @php($photo = $vehicle->getFirstMediaUrl('photos'))
                    @if ($photo)
                        <div class="aspect-[16/10] bg-toco-silver-2">
                            <img src="{{ $photo }}" alt="" class="w-full h-full object-cover">
                        </div>
                    @endif
                    <div class="p-4">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">{{ $vehicle->ref_no }}</p>
                        <p class="font-bold text-toco-navy mt-1">{{ $vehicle->title }}</p>
                    </div>
                </div>

                <div class="bg-white border border-line rounded-sm p-5">
                    <h3 class="font-bold text-toco-navy mb-3">Price summary (USD)</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt>FOB Yokohama</dt><dd class="tabular-nums" x-text="formatUsd(cif.fob)"></dd></div>
                        <div class="flex justify-between border-t pt-2 font-bold text-toco-navy"><dt>CIF total</dt><dd class="tabular-nums" x-text="formatUsd(cif.total)"></dd></div>
                    </dl>
                    <p class="text-[11px] text-ink-soft mt-2 text-ink-soft">CIF includes ocean freight and marine insurance to your chosen port.</p>
                    <p class="text-[11px] text-ink-soft mt-1" x-show="!portId" x-cloak>Pick a destination port to see the CIF total.</p>
                </div>
            </aside>
        </div>
    </section>

    @push('scripts')
    <script>
        function bankCheckout(countries, fob, m3, marineInsuranceUsd, presetCountryId, presetPortId) {
            return {
                countries,
                fob,
                m3,
                marineInsuranceUsd,
                countryId: presetCountryId ? String(presetCountryId) : '',
                portId: presetPortId ? String(presetPortId) : '',
                get selectedCountry() { return this.countries.find(c => c.id == this.countryId); },
                get availablePorts() { return this.selectedCountry ? this.selectedCountry.ports : []; },
                get selectedPort() { return this.availablePorts.find(p => p.id == this.portId); },
                get cif() {
                    const p = this.selectedPort;
                    const rate = p ? Number(p.rate_per_m3 ?? 0) : 0;
                    const freight = +(this.m3 * rate).toFixed(2);
                    const ins = p ? +Number(this.marineInsuranceUsd || 0).toFixed(2) : 0;
                    const total = p ? +(this.fob + freight + ins).toFixed(2) : this.fob;
                    return { fob: this.fob, m3: this.m3, rate, freight, insurance: ins, total };
                },
                onCountryChange() { this.portId = ''; },
                formatUsd(n) {
                    return '$' + Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
                },
            };
        }
    </script>
    @endpush
</x-layouts.site>
