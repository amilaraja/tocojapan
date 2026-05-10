@php
    $title = $vehicle->title.' — Toco Japan';
    $photos = $vehicle->getMedia('photos');
    $heroPhoto = $photos->first()?->getUrl() ?: '/img/v5/car-'.((($vehicle->id % 4) + 1)).'.jpg';
@endphp

<x-layouts.site :title="$title">
    {{-- Breadcrumb --}}
    <div class="bg-toco-silver-2 border-b border-line">
        <div class="max-w-[1440px] mx-auto px-6 py-3 text-[12px] font-mono uppercase tracking-widest text-ink-soft">
            <a href="{{ route('home') }}" class="hover:text-toco-red">Home</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('vehicles.index') }}" class="hover:text-toco-red">Vehicles</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('vehicles.index') }}?make={{ $vehicle->make->slug }}" class="hover:text-toco-red">{{ $vehicle->make->name }}</a>
            <span class="mx-1.5">/</span>
            <span class="text-ink">{{ $vehicle->ref_no }}</span>
        </div>
    </div>

    <section class="max-w-[1440px] mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
            <div class="space-y-4">
                {{-- Photo gallery --}}
                <div class="bg-white border border-line rounded-sm overflow-hidden" x-data="{ active: '{{ $heroPhoto }}' }">
                    <div class="aspect-[16/10] bg-toco-silver-2 grid place-items-center">
                        <img :src="active" alt="{{ $vehicle->title }}" class="w-full h-full object-cover">
                    </div>
                    @if ($photos->count() > 1)
                        <div class="grid grid-cols-6 gap-1 p-1 border-t border-line">
                            @foreach ($photos as $media)
                                <button type="button" @click="active = '{{ $media->getUrl() }}'" class="aspect-[4/3] bg-toco-silver-2 overflow-hidden border border-transparent hover:border-toco-red">
                                    <img src="{{ $media->getUrl() }}" alt="" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Description --}}
                @if ($vehicle->description)
                    <div class="bg-white border border-line rounded-sm p-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">About this vehicle</p>
                        <h2 class="font-bold text-toco-navy text-lg mt-1 mb-3">Description</h2>
                        <div class="prose prose-sm max-w-none text-ink-soft leading-relaxed">{!! nl2br(e($vehicle->description)) !!}</div>
                    </div>
                @endif

                {{-- Features --}}
                @if (! empty($vehicle->features))
                    <div class="bg-white border border-line rounded-sm p-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Equipment</p>
                        <h2 class="font-bold text-toco-navy text-lg mt-1 mb-3">Features</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            @foreach ($vehicle->features as $group => $items)
                                @if (is_array($items))
                                    <div>
                                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-2">{{ str_replace('_', ' ', (string) $group) }}</p>
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($items as $item)
                                                <li class="flex items-center gap-2">
                                                    <svg class="text-toco-red shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12 10 17 19 8"/></svg>
                                                    <span>{{ $item }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sticky aside --}}
            <aside class="space-y-4 lg:sticky lg:top-20 self-start">
                <div class="bg-white border border-line rounded-sm">
                    <div class="border-b-4 border-toco-red px-5 py-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">{{ $vehicle->ref_no }}</p>
                        <h1 class="text-xl font-extrabold text-toco-navy leading-tight mt-1">{{ $vehicle->title }}</h1>
                        <p class="font-mono text-[11px] uppercase tracking-widest text-ink-soft mt-2">FOB Yokohama</p>
                        <p class="font-extrabold text-3xl text-toco-red mt-1">
                            @if ($vehicle->price_on_request)
                                On request
                            @else
                                ${{ number_format((float) $vehicle->price_fob) }}
                                <span class="text-xs text-ink-soft font-bold">{{ $vehicle->currency }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="p-5 space-y-2">
                        <a href="{{ route('register') }}" class="block text-center bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">Request a quote</a>
                        <a href="#" class="block text-center border border-toco-navy hover:bg-toco-silver-2 text-toco-navy font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">Contact a sales rep</a>
                    </div>
                </div>

                {{-- CIF estimator --}}
                @unless ($vehicle->price_on_request || $vehicle->m3 === null || (float) $vehicle->m3 === 0.0)
                    <div class="bg-white border border-line rounded-sm p-5"
                        x-data="{
                            countryId: '', portId: '', ports: [], result: null, error: null, loading: false,
                            countries: @js($countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'iso2' => $c->iso2, 'ports' => $c->ports->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'rate_per_m3' => (float) $p->rate_per_m3])->all()])),
                            onCountry() {
                                this.portId = '';
                                const c = this.countries.find(c => c.id == this.countryId);
                                this.ports = c ? c.ports : [];
                            },
                            async submit() {
                                this.error = null; this.result = null;
                                if (!this.portId) { this.error = 'Pick a port.'; return; }
                                this.loading = true;
                                try {
                                    const r = await fetch('/api/v1/cif/calculate', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                        body: JSON.stringify({ port_id: this.portId, vehicle_slug: '{{ $vehicle->slug }}' })
                                    });
                                    const j = await r.json();
                                    if (!r.ok) { this.error = j.errors?.message || 'Calculation failed.'; }
                                    else { this.result = j.data; }
                                } finally { this.loading = false; }
                            }
                        }">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Landed cost</p>
                        <h3 class="font-bold text-toco-navy text-base mb-3">Estimate CIF to your port</h3>

                        <div class="space-y-2 text-sm">
                            <select x-model="countryId" @change="onCountry()" class="w-full border-line rounded-sm">
                                <option value="">— Country —</option>
                                <template x-for="c in countries" :key="c.id">
                                    <option :value="c.id" x-text="c.name + ' (' + c.iso2 + ')'"></option>
                                </template>
                            </select>
                            <select x-model="portId" :disabled="!ports.length" class="w-full border-line rounded-sm disabled:bg-toco-silver-2">
                                <option value="">— Port —</option>
                                <template x-for="p in ports" :key="p.id">
                                    <option :value="p.id" x-text="p.name + ' · $' + p.rate_per_m3 + '/m³'"></option>
                                </template>
                            </select>
                            <button type="button" @click="submit()" :disabled="loading" class="w-full bg-toco-navy hover:bg-toco-navy-deep disabled:opacity-50 text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">
                                <span x-show="!loading">Calculate</span>
                                <span x-show="loading" x-cloak>Calculating…</span>
                            </button>
                        </div>

                        <div x-show="error" x-cloak class="text-toco-red text-[12px] mt-3" x-text="error"></div>

                        <template x-if="result">
                            <dl class="mt-4 pt-4 border-t border-line space-y-1.5 text-sm">
                                <div class="flex justify-between"><dt class="text-ink-soft text-[12px]">FOB</dt><dd class="font-semibold tabular-nums" x-text="'$' + Number(result.price_fob).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd></div>
                                <div class="flex justify-between"><dt class="text-ink-soft text-[12px]">Freight</dt><dd class="font-semibold tabular-nums" x-text="'$' + Number(result.freight).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd></div>
                                <div class="flex justify-between"><dt class="text-ink-soft text-[12px]">Insurance</dt><dd class="font-semibold tabular-nums" x-text="'$' + Number(result.insurance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd></div>
                                <div class="flex justify-between border-t border-line pt-1.5 mt-1"><dt class="font-bold text-toco-navy">CIF</dt><dd class="font-extrabold text-toco-navy tabular-nums" x-text="'$' + Number(result.cif_total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd></div>
                                <p class="text-[11px] text-ink-soft leading-snug pt-1">Estimate only. Land charges in destination country not included.</p>
                            </dl>
                        </template>
                    </div>
                @endunless

                {{-- Specs --}}
                <div class="bg-white border border-line rounded-sm p-5 text-sm">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-3">Specifications</p>
                    <dl class="grid grid-cols-2 gap-y-1.5">
                        @foreach ([
                            ['Make', $vehicle->make->name ?? '—'],
                            ['Model', $vehicle->vehicleModel->name ?? '—'],
                            ['Body type', $vehicle->bodyType->name ?? '—'],
                            ['Year', $vehicle->year_first_reg],
                            ['Mileage', number_format((int) $vehicle->mileage_km).' km'],
                            ['Engine', $vehicle->engine_cc.' cc'],
                            ['Fuel', ucfirst((string) $vehicle->fuel)],
                            ['Transmission', ucfirst((string) $vehicle->transmission)],
                            ['Drive', strtoupper((string) $vehicle->drive)],
                            ['Steering', $vehicle->steering_side === 'right' ? 'RHD' : 'LHD'],
                            ['Doors / Seats', $vehicle->doors.' / '.$vehicle->seats],
                            ['Exterior', $vehicle->exterior_color ?? '—'],
                            ['Interior', $vehicle->interior_color ?? '—'],
                            ['Dimensions', $vehicle->length_cm.'×'.$vehicle->width_cm.'×'.$vehicle->height_cm.' cm'],
                            ['M³ shipping', $vehicle->m3],
                            ['Warranty', $vehicle->warranty_period ?? '—'],
                        ] as $row)
                            <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest pt-1">{{ $row[0] }}</dt>
                            <dd class="text-right font-semibold pt-1">{{ $row[1] }}</dd>
                        @endforeach
                    </dl>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.site>
