@php
    $title = $vehicle->title.' — Toco Japan';
    $photos = $vehicle->getMedia('photos');
    $photoUrls = $photos->map(fn ($m) => $m->getUrl())->values();
    if ($photoUrls->isEmpty()) {
        $photoUrls = collect(['/img/v5/car-'.((($vehicle->id % 4) + 1)).'.jpg']);
    }
@endphp

<x-layouts.site :title="$title">
    {{-- Breadcrumb --}}
    <div class="bg-toco-silver-2 border-b border-line">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-3 text-[12px] font-mono uppercase tracking-widest text-ink-soft">
            <a href="{{ route('home') }}" class="hover:text-toco-red">Home</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('vehicles.index') }}" class="hover:text-toco-red">Vehicles</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('vehicles.index') }}?make={{ $vehicle->make->slug }}" class="hover:text-toco-red">{{ $vehicle->make->name }}</a>
            <span class="mx-1.5">/</span>
            <span class="text-ink">{{ $vehicle->ref_no }}</span>
        </div>
    </div>

    <section class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-[2fr_1fr] gap-6">
            <div class="space-y-4">
                {{-- Photo gallery --}}
                <div
                    class="bg-white border border-line rounded-sm overflow-hidden"
                    x-data="vehicleGallery({{ $photoUrls->toJson() }})"
                    x-effect="document.body.style.overflow = lightbox ? 'hidden' : ''"
                    @keydown.window.left.prevent="prev()"
                    @keydown.window.right.prevent="next()"
                    @keydown.window.escape="closeLightbox()"
                >
                    {{-- Hero image with overlay nav --}}
                    <div class="relative aspect-[16/10] bg-toco-silver-2 group">
                        <img
                            :src="photos[index]"
                            alt="{{ $vehicle->title }}"
                            class="w-full h-full object-cover cursor-zoom-in"
                            @click="openLightbox()"
                        >

                        @if (count($photoUrls) > 1)
                            <button
                                type="button"
                                @click.stop="prev()"
                                aria-label="Previous photo"
                                class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/45 hover:bg-black/70 text-white grid place-items-center opacity-0 group-hover:opacity-100 focus:opacity-100 transition"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <button
                                type="button"
                                @click.stop="next()"
                                aria-label="Next photo"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/45 hover:bg-black/70 text-white grid place-items-center opacity-0 group-hover:opacity-100 focus:opacity-100 transition"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                            <div class="absolute bottom-3 right-3 bg-black/55 text-white text-[11px] font-mono tracking-widest px-2 py-1 rounded">
                                <span x-text="index + 1"></span> / <span x-text="photos.length"></span>
                            </div>
                        @endif

                        <button
                            type="button"
                            @click.stop="openLightbox()"
                            aria-label="Open fullscreen"
                            class="absolute top-3 right-3 w-9 h-9 rounded-full bg-black/45 hover:bg-black/70 text-white grid place-items-center opacity-0 group-hover:opacity-100 focus:opacity-100 transition"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                        </button>
                    </div>

                    @if (count($photoUrls) > 1)
                        <div class="grid grid-cols-6 gap-1 p-1 border-t border-line">
                            @foreach ($photoUrls as $i => $url)
                                <button
                                    type="button"
                                    @click="goTo({{ $i }})"
                                    :class="index === {{ $i }} ? 'border-toco-red' : 'border-transparent hover:border-toco-red'"
                                    class="aspect-[4/3] bg-toco-silver-2 overflow-hidden border-2 transition"
                                >
                                    <img src="{{ $url }}" alt="" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Lightbox --}}
                    <div
                        x-show="lightbox"
                        x-cloak
                        x-transition.opacity
                        @click.self="closeLightbox()"
                        @wheel.prevent="onWheel($event)"
                        @pointermove.window="onDrag($event)"
                        @pointerup.window="endDrag($event)"
                        @pointercancel.window="endDrag($event)"
                        class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4 overflow-hidden select-none touch-none"
                        style="display: none;"
                    >
                        <button
                            type="button"
                            @click="closeLightbox()"
                            aria-label="Close"
                            class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center z-10"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>

                        {{-- Zoom controls --}}
                        <div class="absolute top-4 left-1/2 -translate-x-1/2 flex items-center gap-1 bg-white/10 backdrop-blur rounded-full p-1 z-10">
                            <button type="button" @click.stop="zoomOut()" :disabled="zoom <= minZoom" aria-label="Zoom out" class="w-9 h-9 rounded-full text-white hover:bg-white/15 disabled:opacity-30 disabled:cursor-not-allowed grid place-items-center">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg>
                            </button>
                            <span class="text-white/90 text-xs font-mono tracking-widest w-12 text-center" x-text="Math.round(zoom * 100) + '%'"></span>
                            <button type="button" @click.stop="zoomIn()" :disabled="zoom >= maxZoom" aria-label="Zoom in" class="w-9 h-9 rounded-full text-white hover:bg-white/15 disabled:opacity-30 disabled:cursor-not-allowed grid place-items-center">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                            </button>
                            <button type="button" @click.stop="resetZoom()" :disabled="zoom === 1 && panX === 0 && panY === 0" aria-label="Reset zoom" class="w-9 h-9 rounded-full text-white hover:bg-white/15 disabled:opacity-30 disabled:cursor-not-allowed grid place-items-center">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/></svg>
                            </button>
                        </div>

                        <img
                            x-ref="zoomImage"
                            :src="photos[index]"
                            alt="{{ $vehicle->title }}"
                            class="max-w-full max-h-[88vh] object-contain shadow-2xl will-change-transform touch-none"
                            :style="{ transform: transform, cursor: zoom > 1 ? (dragging ? 'grabbing' : 'grab') : 'zoom-in', transition: (dragging || activePointerCount >= 2) ? 'none' : 'transform 150ms' }"
                            draggable="false"
                            @click.stop
                            @dblclick.stop="toggleZoom($event)"
                            @pointerdown.stop="startDrag($event); $event.target.setPointerCapture && $event.target.setPointerCapture($event.pointerId)"
                        >

                        @if (count($photoUrls) > 1)
                            <button
                                type="button"
                                @click.stop="prev()"
                                aria-label="Previous photo"
                                class="absolute left-4 md:left-8 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/25 text-white grid place-items-center z-10"
                            >
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <button
                                type="button"
                                @click.stop="next()"
                                aria-label="Next photo"
                                class="absolute right-4 md:right-8 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/25 text-white grid place-items-center z-10"
                            >
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                            </button>

                            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/90 text-sm font-mono tracking-widest">
                                <span x-text="index + 1"></span> / <span x-text="photos.length"></span>
                            </div>
                        @endif
                    </div>
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
            <aside class="space-y-4 md:sticky md:top-20 self-start">
                <div class="bg-white border border-line rounded-sm">
                    <div class="border-b-4 border-toco-red px-5 py-5 relative">
                        @php($isFavorited = in_array($vehicle->id, $favoritedIds ?? [], true))
                        <form method="POST" action="{{ route('favorites.toggle', $vehicle->slug) }}" class="absolute top-4 right-4">
                            @auth @csrf @endauth
                            <button type="{{ Auth::check() ? 'submit' : 'button' }}"
                                @guest onclick="window.location='{{ route('login') }}'" @endguest
                                aria-label="{{ $isFavorited ? 'Remove from wishlist' : 'Save to wishlist' }}"
                                title="{{ $isFavorited ? 'Saved to your wishlist' : 'Save to wishlist' }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-sm border text-[11px] font-bold uppercase tracking-widest transition
                                    {{ $isFavorited ? 'bg-toco-red text-white border-toco-red' : 'bg-white text-toco-navy border-line hover:border-toco-red hover:text-toco-red' }}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.5-7 10-7 10Z"/></svg>
                                {{ $isFavorited ? 'Saved' : 'Wishlist' }}
                            </button>
                        </form>
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">{{ $vehicle->ref_no }}</p>
                        <h1 class="text-xl font-extrabold text-toco-navy leading-tight mt-1 pr-24">{{ $vehicle->title }}</h1>
                        <p class="font-mono text-[11px] uppercase tracking-widest text-ink-soft mt-2">FOB Yokohama</p>
                        <p class="font-extrabold text-3xl text-toco-red mt-1">
                            @if ($vehicle->price_on_request)
                                On request
                            @else
                                @money($vehicle->price_fob)
                            @endif
                        </p>
                    </div>
                    <div class="p-5 space-y-2">
                        @php
                            $payment = app(\App\Settings\PaymentSettings::class);
                            $paypalMode = config('paypal.mode', 'sandbox');
                            $paypalReady = $payment->paypal_enabled
                                && ! empty(config("paypal.{$paypalMode}.client_id"))
                                && ! empty(config("paypal.{$paypalMode}.client_secret"));
                            $bankReady = $payment->bank_transfer_enabled;
                            $buyable = ! $vehicle->price_on_request && $vehicle->price_fob > 0;
                        @endphp
                        @auth
                            @if ($buyable && $paypalReady)
                                <form method="POST" action="{{ route('checkout.start', $vehicle->slug) }}">
                                    @csrf
                                    <button type="submit" class="w-full text-center bg-toco-navy hover:bg-toco-navy-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm inline-flex items-center justify-center gap-2">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H7z"/></svg>
                                        Buy with PayPal — @money($vehicle->price_fob)
                                    </button>
                                </form>
                            @endif
                            @if ($buyable && $bankReady)
                                <a href="{{ route('checkout.bank.show', $vehicle->slug) }}" class="block w-full text-center bg-toco-navy hover:bg-toco-navy-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
                                    <span class="inline-flex items-center justify-center gap-2">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
                                        Buy with bank transfer
                                    </span>
                                </a>
                            @endif
                            @if ($buyable && ! $paypalReady && ! $bankReady)
                                <div class="w-full text-center bg-toco-silver-2 text-ink-soft border border-dashed border-line font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
                                    Buy now — coming soon
                                </div>
                            @endif
                            @error('paypal')
                                <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">{{ $message }}</div>
                            @enderror
                            @error('payment')
                                <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">{{ $message }}</div>
                            @enderror
                            @error('vehicle')
                                <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">{{ $message }}</div>
                            @enderror
                            <a href="#quote-form" class="block text-center bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">Request a quote</a>
                        @else
                            <a href="{{ route('login') }}" class="block text-center bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">Sign in to buy or quote</a>
                        @endauth
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

        @auth
            {{-- Request-a-quote form --}}
            <div id="quote-form" class="bg-white border border-line border-t-4 border-t-toco-red rounded-sm p-6 mt-8 max-w-3xl"
                x-data="{
                    countryId: '', portId: '', ports: [],
                    countries: @js($countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'iso2' => $c->iso2, 'ports' => $c->ports->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->all()])),
                    onCountry() {
                        this.portId = '';
                        const c = this.countries.find(c => c.id == this.countryId);
                        this.ports = c ? c.ports : [];
                    }
                }">
                <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Request a quote</p>
                <h2 class="font-extrabold text-toco-navy text-xl mt-1">Get a CIF estimate for {{ $vehicle->title }}</h2>
                <p class="text-sm text-ink-soft mt-1">Tell us where you'd like it delivered. We'll come back with a final figure within one business day.</p>

                <form method="POST" action="{{ route('quotes.store', $vehicle->slug) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-5 text-sm">
                    @csrf
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Your name</label>
                        <input type="text" name="contact_name" value="{{ old('contact_name', Auth::user()->name) }}" required class="w-full border-line rounded-sm">
                        @error('contact_name')<p class="text-toco-red text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Email</label>
                        <input type="email" name="contact_email" value="{{ old('contact_email', Auth::user()->email) }}" required class="w-full border-line rounded-sm">
                        @error('contact_email')<p class="text-toco-red text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Phone (optional)</label>
                        <input type="text" name="contact_phone" value="{{ old('contact_phone', Auth::user()->phone) }}" class="w-full border-line rounded-sm">
                    </div>
                    <div></div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Destination country</label>
                        <select name="country_id" x-model="countryId" @change="onCountry()" class="w-full border-line rounded-sm">
                            <option value="">— Country —</option>
                            <template x-for="c in countries" :key="c.id">
                                <option :value="c.id" x-text="c.name + ' (' + c.iso2 + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Port</label>
                        <select name="port_id" x-model="portId" :disabled="!ports.length" class="w-full border-line rounded-sm disabled:bg-toco-silver-2">
                            <option value="">— Port —</option>
                            <template x-for="p in ports" :key="p.id">
                                <option :value="p.id" x-text="p.name"></option>
                            </template>
                        </select>
                        @error('port_id')<p class="text-toco-red text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Message (optional)</label>
                        <textarea name="message" rows="4" maxlength="4000" placeholder="Anything we should know? (target accessories, alternative models, currency, ETA…)" class="w-full border-line rounded-sm">{{ old('message') }}</textarea>
                        @error('message')<p class="text-toco-red text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-5 py-3 rounded-sm">Submit quote request</button>
                    </div>
                </form>
            </div>
        @endauth
    </section>
</x-layouts.site>
