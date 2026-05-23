@php
    $title = 'CIF shipping calculator — Toco Japan';
    $description = 'Calculate the CIF total from any Japanese vehicle\'s FOB price, m³ volume and destination port. Live freight rates, insurance pre-applied. Free, no signup.';
@endphp

<x-layouts.site :title="$title" :description="$description">
    {{-- Page header --}}
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-10">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">Tools</p>
            <h1 class="text-2xl md:text-3xl font-extrabold mt-1">CIF calculator</h1>
            <p class="text-white/70 text-sm mt-2 max-w-2xl">Estimate your landed cost (FOB + freight + insurance) to any of our destination ports. Pick a vehicle from stock, or enter your own price + dimensions.</p>
        </div>
    </section>

    <section class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-8">
        <div
            x-data="{
                tab: 'vehicle',
                countryId: '',
                portId: '',
                ports: [],
                vehicleSlug: '',
                priceFob: '',
                m3: '',
                length: '',
                width: '',
                height: '',
                result: null,
                error: null,
                loading: false,
                countries: @js($countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'iso2' => $c->iso2, 'ports' => $c->ports->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'rate_per_m3' => (float) $p->rate_per_m3])->all()])),
                init() {
                    // Pre-fill from the destination saved earlier (toco_port cookie).
                    // Applied via $nextTick so the <option> x-for templates have
                    // rendered before the <select>s sync to the saved values.
                    const dc = '{{ $destPort?->country_id }}', dp = '{{ $destPort?->id }}';
                    if (! dc) return;
                    const c = this.countries.find(c => c.id == dc);
                    this.ports = c ? c.ports : [];
                    this.$nextTick(() => {
                        this.countryId = dc;
                        this.$nextTick(() => { this.portId = dp; });
                    });
                },
                onCountry() {
                    this.portId = '';
                    const c = this.countries.find(c => c.id == this.countryId);
                    this.ports = c ? c.ports : [];
                },
                saveDestination() {
                    if (!this.portId) return;
                    fetch('{{ route('destination.set') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: 'port_id=' + encodeURIComponent(this.portId),
                    }).catch(() => {});
                },
                recalcM3() {
                    const l = parseFloat(this.length) || 0;
                    const w = parseFloat(this.width) || 0;
                    const h = parseFloat(this.height) || 0;
                    if (l > 0 && w > 0 && h > 0) {
                        this.m3 = (l * w * h / 1000000).toFixed(4);
                    }
                },
                async submit() {
                    this.error = null;
                    this.result = null;
                    if (!this.portId) { this.error = 'Please pick a destination port.'; return; }
                    const payload = { port_id: this.portId };
                    if (this.tab === 'vehicle') {
                        if (!this.vehicleSlug) { this.error = 'Please pick a vehicle.'; return; }
                        payload.vehicle_slug = this.vehicleSlug;
                    } else {
                        const p = parseFloat(this.priceFob), m = parseFloat(this.m3);
                        if (!(p > 0) || !(m > 0)) { this.error = 'Enter a valid price and m³.'; return; }
                        payload.price_fob = p;
                        payload.m3 = m;
                    }
                    this.loading = true;
                    try {
                        const r = await fetch('/api/v1/cif/calculate', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const j = await r.json();
                        if (!r.ok) {
                            this.error = j.errors?.message || 'Calculation failed.';
                        } else {
                            this.result = j.data;
                        }
                    } catch (e) {
                        this.error = 'Network error: ' + e.message;
                    } finally {
                        this.loading = false;
                    }
                }
            }"
            class="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-6 items-start"
        >
            {{-- Form panel --}}
            <div class="bg-white border border-line rounded-sm">
                <div class="border-b border-line px-5 py-3 flex gap-1">
                    @foreach (['vehicle' => 'From stock', 'manual' => 'Manual entry'] as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'text-toco-red border-toco-red' : 'text-ink-soft border-transparent hover:text-ink'"
                            class="text-[12px] font-bold uppercase tracking-widest px-3 py-2.5 border-b-2 -mb-[13px] transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <form @submit.prevent="submit()" class="p-5 space-y-4">
                    {{-- Destination --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Destination country</label>
                            <select x-model="countryId" @change="onCountry()" class="w-full border-line rounded-sm">
                                <option value="">— Pick a country —</option>
                                <template x-for="c in countries" :key="c.id">
                                    <option :value="c.id" x-text="c.name + ' (' + c.iso2 + ')'"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Port</label>
                            <select x-model="portId" @change="saveDestination()" :disabled="!ports.length" class="w-full border-line rounded-sm disabled:bg-toco-silver-2">
                                <option value="">— Pick a port —</option>
                                <template x-for="p in ports" :key="p.id">
                                    <option :value="p.id" x-text="p.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    {{-- Vehicle picker --}}
                    <div x-show="tab === 'vehicle'">
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Vehicle from stock</label>
                        <select x-model="vehicleSlug" class="w-full border-line rounded-sm">
                            <option value="">— Pick a vehicle —</option>
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->slug }}">{{ $v->title }} ({{ $v->stock_no ?: $v->ref_no }}) — ${{ number_format((float) ($v->effectivePriceFob() ?? $v->price_fob)) }}</option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-ink-soft mt-1">Showing the latest 50 vehicles in stock.</p>
                    </div>

                    {{-- Manual --}}
                    <div x-show="tab === 'manual'" x-cloak class="space-y-3">
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">FOB price (USD)</label>
                            <input type="number" min="0" step="100" x-model="priceFob" class="w-full border-line rounded-sm" placeholder="e.g. 5500">
                        </div>
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Vehicle dimensions (cm)</label>
                            <div class="grid grid-cols-3 gap-2">
                                <input type="number" min="0" x-model="length" @input="recalcM3()" placeholder="Length" class="border-line rounded-sm">
                                <input type="number" min="0" x-model="width"  @input="recalcM3()" placeholder="Width"  class="border-line rounded-sm">
                                <input type="number" min="0" x-model="height" @input="recalcM3()" placeholder="Height" class="border-line rounded-sm">
                            </div>
                        </div>
                    </div>

                    <button type="submit" :disabled="loading" class="w-full bg-toco-red hover:bg-toco-red-deep disabled:opacity-50 text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
                        <span x-show="!loading">Calculate CIF</span>
                        <span x-show="loading" x-cloak>Calculating…</span>
                    </button>

                    <div x-show="error" x-cloak class="text-toco-red text-sm bg-[#FDECEE] border border-toco-red/30 px-3 py-2 rounded-sm" x-text="error"></div>
                </form>
            </div>

            {{-- Result panel --}}
            <aside class="lg:sticky lg:top-20 self-start">
                <x-cif-breakdown />
            </aside>
        </div>
    </section>
</x-layouts.site>
