@php
    $photos = $vehicle->getMedia('photos');
    // Hero carousel: 1280px WebP. Thumb strip: 300px WebP. Lightbox: originals.
    $photoUrls = $photos->map(fn ($m) => $m->hasGeneratedConversion('gallery') ? $m->getUrl('gallery') : $m->getUrl())->values();
    $thumbUrls = $photos->map(fn ($m) => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl())->values();
    $fullUrls = $photos->map(fn ($m) => $m->getUrl())->values();
    if ($photoUrls->isEmpty()) {
        $photoUrls = $thumbUrls = $fullUrls = collect(['/img/v5/car-'.((($vehicle->id % 4) + 1)).'.jpg']);
    }
    $videoUrl = $vehicle->videoUrl();

    // Countries + ports for the CIF forms, with the import regulation that
    // applies to each port (a port-specific rule wins over a country-wide one).
    $countriesData = $countries->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'iso2' => $c->iso2,
        'ports' => $c->ports->map(function ($p) use ($c) {
            $reg = $c->importRegulations->first(fn ($r) => $r->ports->contains('id', $p->id))
                ?: $c->importRegulations->first(fn ($r) => $r->ports->isEmpty());

            return [
                'id' => $p->id,
                'name' => $p->name,
                'rate_per_m3' => (float) $p->rate_per_m3,
                'regulation' => $reg ? [
                    'description' => $reg->short_description,
                    'age_limit' => $reg->year_restriction,
                    'shipment_time' => $reg->time_of_shipment,
                    'notes' => $reg->comments,
                ] : null,
            ];
        })->all(),
    ])->all();

    // Buy-now state — hoisted to top so it's in scope regardless of any
    // x-component closures further down in the view.
    $payment = app(\App\Settings\PaymentSettings::class);
    $paypalMode = config('paypal.mode', 'sandbox');
    $paypalReady = $payment->paypal_enabled
        && ! empty(config("paypal.{$paypalMode}.client_id"))
        && ! empty(config("paypal.{$paypalMode}.client_secret"));
    $bankReady = $payment->bank_transfer_enabled;
    $isSold = $vehicle->status === 'sold';
    $buyable = ! $isSold && ! $vehicle->price_on_request && $vehicle->effectivePriceFob() > 0;

    // --- SEO meta ---
    // Title format: "{Year} {Make} {Model} for sale — Toco Japan"
    // Description packs the distinguishing facts (body, mileage, transmission,
    // fuel, engine, ref, price) so it scores higher on long-tail queries.
    $vehTitleNice = trim(($vehicle->year_first_reg ? $vehicle->year_first_reg.' ' : '').\Illuminate\Support\Str::title((string) ($vehicle->make->name ?? '')).' '.\Illuminate\Support\Str::title((string) ($vehicle->vehicleModel->name ?? '')));
    $vehTitleNice = $vehTitleNice !== '' ? $vehTitleNice : $vehicle->title;

    $seoTitle = ($vehicle->seo['title'] ?? null) ?: ($vehTitleNice.' for sale — Toco Japan');

    $descBits = [];
    if ($vehicle->bodyType?->name) $descBits[] = $vehicle->bodyType->name;
    if ($vehicle->mileage_km) $descBits[] = number_format((int) $vehicle->mileage_km).'km';
    if ($vehicle->transmission) $descBits[] = strtolower($vehicle->transmission);
    if ($vehicle->fuel) $descBits[] = strtolower($vehicle->fuel);
    if ($vehicle->engine_cc) $descBits[] = ((int) $vehicle->engine_cc).'cc';
    $descCore = $descBits ? ' — '.implode(', ', $descBits).'.' : '.';
    $descPrice = ! $vehicle->price_on_request && $vehicle->effectivePriceFob() > 0
        ? ' FOB Japan from $'.number_format((float) $vehicle->effectivePriceFob()).'.'
        : '';
    $descRef = $vehicle->ref_no ? ' Ref '.$vehicle->ref_no.'.' : '';

    $seoDescription = ($vehicle->seo['description'] ?? null) ?: \Illuminate\Support\Str::limit(
        $vehTitleNice.$descCore.$descPrice.$descRef.' Buy + ship worldwide with CIF to your port.',
        155
    );

    $title = $seoTitle;

    // ---- Vehicle Details block (two-column spec table per the v6 design) ----
    $dash = '—';
    $em = function ($v) use ($dash) { return ($v === null || $v === '' || $v === 0 || $v === '0') ? $dash : $v; };
    // Dimensions: stored in cm (decimal:2) but displayed in metres for
    // public consumption — "426.00 cm" → "4.26 m". Strips trailing zeros
    // so a clean "4.5" doesn't render as "4.50".
    $fmtDim = function ($v) {
        if ($v === null || $v === '' || (float) $v <= 0) {
            return null;
        }
        $m = (float) $v / 100;

        return rtrim(rtrim(number_format($m, 2, '.', ''), '0'), '.');
    };
    $dimParts = [$fmtDim($vehicle->length_cm), $fmtDim($vehicle->width_cm), $fmtDim($vehicle->height_cm)];
    $dimension = array_filter($dimParts) !== []
        ? implode(' × ', array_map(fn ($p) => $p ?? '?', $dimParts)) . ' m'
        : $dash;
    $steeringDisplay = $vehicle->steering_side
        ? ($vehicle->steering_side === 'right' ? 'Right hand drive' : 'Left hand drive')
        : $dash;
    $detailsLeft = [
        ['Stock no.', $em($vehicle->stock_no)],
        ['Make', $em(optional($vehicle->make)->name)],
        ['Model', $em(optional($vehicle->vehicleModel)->name)],
        ['Grade', $em($vehicle->grade)],
        ['VIN / Chassis no.', $em($vehicle->chassis_number)],
        ['Model code', $em($vehicle->model_code)],
        ['Engine', $vehicle->engine_cc ? number_format((int) $vehicle->engine_cc) . ' cc' : $dash],
        ['Drive', $em(strtoupper((string) $vehicle->drive))],
        ['Transmission', $em(ucfirst((string) $vehicle->transmission))],
        ['Body type', $em(optional($vehicle->bodyType)->name)],
        ['Location', $em($vehicle->location ?: 'Tochigi, Japan')],
    ];
    $detailsRight = [
        ['Registration Y/M', $em($vehicle->registrationYmDisplay())],
        ['Manufacture Y/M', $em($vehicle->manufactureYmDisplay())],
        ['Mileage', $vehicle->mileage_km ? number_format((int) $vehicle->mileage_km) . ' km' : $dash],
        ['Fuel', $em(ucfirst((string) $vehicle->fuel))],
        ['Steering', $steeringDisplay],
        ['Doors', $em($vehicle->doors)],
        ['Seats', $em($vehicle->seats)],
        ['Exterior colour', $em($vehicle->exterior_color)],
        ['Interior colour', $em($vehicle->interior_color)],
        ['Dimension', $dimension],
        ['M3', $vehicle->m3 ? number_format((float) $vehicle->m3, 3) : $dash],
    ];

    // CIF add-ons + global options list (CR-2026-05-28). Loaded here to
    // avoid opening a second @php block further down — see
    // feedback_blade_php_shortform memory.
    $cifSettings = app(\App\Settings\CifSettings::class);
    $vehicleOptions = \App\Models\VehicleOption::query()
        ->where('is_active', true)
        ->orderBy('sort_order')->orderBy('id')
        ->get(['id', 'name', 'price', 'tooltip']);
@endphp

<x-layouts.site :title="$title" :description="$seoDescription" :ogImage="$photoUrls->first()">
    @push('head')
        @php
            $vehicleSchema = [
                '@context' => 'https://schema.org',
                '@type' => ['Vehicle', 'Product'],
                'name' => $vehTitleNice,
                'sku' => $vehicle->ref_no,
                'image' => $photoUrls->take(10)->values()->all(),
                'description' => $seoDescription,
                'url' => url()->current(),
                'brand' => ['@type' => 'Brand', 'name' => $vehicle->make->name ?? null],
                'manufacturer' => ['@type' => 'Organization', 'name' => $vehicle->make->name ?? null],
                'model' => $vehicle->vehicleModel->name ?? null,
                'modelDate' => $vehicle->year_first_reg,
                'vehicleModelDate' => $vehicle->year_first_reg,
                'productionDate' => $vehicle->year_first_reg ? (string) $vehicle->year_first_reg : null,
                'bodyType' => $vehicle->bodyType->name ?? null,
                'numberOfDoors' => $vehicle->doors ?: null,
                'seatingCapacity' => $vehicle->seats ?: null,
                'color' => $vehicle->exterior_color ?: null,
                'vehicleInteriorColor' => $vehicle->interior_color ?: null,
                'fuelType' => $vehicle->fuel ?: null,
                'vehicleTransmission' => $vehicle->transmission ?: null,
                'driveWheelConfiguration' => $vehicle->drive ?: null,
                'steeringPosition' => $vehicle->steering_side ?: null,
                'itemCondition' => 'https://schema.org/UsedCondition',
                'mileageFromOdometer' => $vehicle->mileage_km ? [
                    '@type' => 'QuantitativeValue',
                    'value' => (int) $vehicle->mileage_km,
                    'unitCode' => 'KMT',
                ] : null,
                'vehicleEngine' => $vehicle->engine_cc ? [
                    '@type' => 'EngineSpecification',
                    'engineDisplacement' => [
                        '@type' => 'QuantitativeValue',
                        'value' => (int) $vehicle->engine_cc,
                        'unitCode' => 'CMQ',
                    ],
                ] : null,
                'offers' => ($vehicle->price_on_request || ! $vehicle->effectivePriceFob())
                    ? null
                    : [
                        '@type' => 'Offer',
                        'price' => (float) $vehicle->effectivePriceFob(),
                        'priceCurrency' => 'USD',
                        'availability' => $isSold ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
                        'itemCondition' => 'https://schema.org/UsedCondition',
                        'seller' => [
                            '@type' => 'AutoDealer',
                            'name' => config('app.name', 'Toco Japan'),
                            'url' => url('/'),
                        ],
                    ],
            ];
            $vehicleSchema = array_filter($vehicleSchema, fn ($v) => $v !== null && $v !== '' && $v !== []);
        @endphp
        <script type="application/ld+json">
        {!! json_encode($vehicleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @endpush

    @push('scripts')
    <script>
        // Track this vehicle in the visitor's localStorage so the "Recently
        // viewed" strip on the homepage can show it back to them. Stores
        // slugs only (most-recent first), capped at 8 entries.
        (function () {
            try {
                const KEY = 'toco_recent_vehicles';
                const slug = @json($vehicle->slug);
                if (!slug) return;
                let list = [];
                try { list = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) {}
                if (!Array.isArray(list)) list = [];
                list = [slug, ...list.filter(s => s !== slug)].slice(0, 8);
                localStorage.setItem(KEY, JSON.stringify(list));
            } catch (e) { /* localStorage blocked — ignore */ }
        })();

        // CIF estimator with add-ons + options. Lives in a window-scoped factory
        // so the Alpine x-data on the calculator card stays a one-liner.
        window.cifCalc = function (cfg) {
            return {
                countries: cfg.countries,
                options: cfg.options,
                marineInsuranceFee: cfg.marineInsuranceFee,
                maintenanceFee: cfg.maintenanceFee,
                preInspectionFee: cfg.preInspectionFee,
                countryId: '', portId: '', ports: [],
                result: null, error: null, loading: false,
                marine: true,                  // marine insurance opt-in default ON
                maintenance: false,            // maintenance package default OFF
                preInspection: false,          // pre-inspection fee default OFF
                selectedOptions: [],           // array of vehicle_option ids
                init() {
                    if (cfg.preCountryId) {
                        const c = this.countries.find(c => c.id == cfg.preCountryId);
                        if (c) {
                            this.ports = c.ports;
                            this.$nextTick(() => {
                                this.countryId = cfg.preCountryId;
                                this.$nextTick(() => { this.portId = cfg.prePortId; });
                            });
                        }
                    }
                },
                onCountry() {
                    this.portId = '';
                    const c = this.countries.find(c => c.id == this.countryId);
                    this.ports = c ? c.ports : [];
                },
                regulation() {
                    const p = this.ports.find(p => p.id == this.portId);
                    return p && p.regulation ? p.regulation : null;
                },
                async submit() {
                    this.error = null; this.result = null;
                    if (!this.portId) { this.error = 'Pick a port.'; return; }
                    this.loading = true;
                    try {
                        const r = await fetch('/api/v1/cif/calculate', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ port_id: this.portId, vehicle_slug: cfg.slug })
                        });
                        const j = await r.json();
                        if (!r.ok) { this.error = j.errors?.message || j.message || 'Calculation failed.'; }
                        else { this.result = j.data; }
                    } finally { this.loading = false; }
                },
                marineFee() { return this.marine ? Number(this.marineInsuranceFee ?? 0) : 0; },
                isSelected(optId) {
                    // Checkbox :value pushes strings to the array; option ids are
                    // numbers. Compare loosely so the totals actually update.
                    return this.selectedOptions.some(s => Number(s) === Number(optId));
                },
                pricedOptionsTotal() {
                    return this.options
                        .filter(o => this.isSelected(o.id) && o.price !== null)
                        .reduce((sum, o) => sum + Number(o.price), 0);
                },
                hasAskSelected() {
                    return this.options.some(o => this.isSelected(o.id) && o.price === null);
                },
                grandTotal() {
                    if (!this.result) return 0;
                    const base = Number(this.result.price_fob ?? 0) + Number(this.result.freight ?? 0);
                    const extras = this.marineFee()
                        + (this.maintenance ? this.maintenanceFee : 0)
                        + (this.preInspection ? this.preInspectionFee : 0)
                        + this.pricedOptionsTotal();
                    return base + extras;
                },
                fmt(n) { return '$' + Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }); }
            };
        };
    </script>
    @endpush

    {{-- Breadcrumb --}}
    <div class="bg-toco-silver-2 border-b border-line">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-3 text-[12px] font-mono uppercase tracking-widest text-ink-soft">
            <a href="{{ route('home') }}" class="hover:text-toco-red">Home</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('vehicles.index') }}" class="hover:text-toco-red">Vehicles</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('vehicles.index') }}?make={{ $vehicle->make->slug }}" class="hover:text-toco-red">{{ $vehicle->make->name }}</a>
            <span class="mx-1.5">/</span>
            <span class="text-ink">{{ $vehicle->stock_no ?: $vehicle->ref_no }}</span>
        </div>
    </div>

    <section class="max-w-[1600px] mx-auto px-6 2xl:px-8 pt-6 pb-2">
        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">{{ $vehicle->stock_no ? 'Stock #'.$vehicle->stock_no : $vehicle->ref_no }}</p>
        <h1 class="text-2xl md:text-3xl font-extrabold text-toco-navy leading-tight mt-1">
            {{ $vehTitleNice }} for sale
            @if ($vehicle->bodyType?->name)<span class="text-ink-soft font-semibold"> — {{ $vehicle->bodyType->name }}</span>@endif
        </h1>
        @if ($vehicle->mileage_km || $vehicle->transmission || $vehicle->fuel)
            <p class="text-sm text-ink-soft mt-1">
                {{ collect([
                    $vehicle->mileage_km ? number_format((int) $vehicle->mileage_km).' km' : null,
                    $vehicle->transmission ? \Illuminate\Support\Str::title($vehicle->transmission) : null,
                    $vehicle->fuel ? \Illuminate\Support\Str::title($vehicle->fuel) : null,
                    $vehicle->engine_cc ? ((int) $vehicle->engine_cc).'cc' : null,
                ])->filter()->implode(' · ') }}
            </p>
        @endif
    </section>

    <section class="max-w-[1600px] mx-auto px-6 2xl:px-8 pb-8">
        <div class="grid grid-cols-1 md:grid-cols-[2fr_1fr] gap-6">
            <div class="space-y-4">
                @if ($isSold)
                    <div class="bg-toco-red text-white text-center font-mono uppercase tracking-[0.3em] text-sm font-bold py-3 rounded-sm">
                        SOLD · this vehicle is no longer available
                    </div>
                @endif

                {{-- Photo gallery --}}
                <div
                    class="bg-white border border-line rounded-sm overflow-hidden"
                    x-data="vehicleGallery({{ $photoUrls->toJson() }}, {{ $fullUrls->toJson() }})"
                    x-effect="document.body.style.overflow = lightbox ? 'hidden' : ''"
                    @keydown.window.left.prevent="prev()"
                    @keydown.window.right.prevent="next()"
                    @keydown.window.escape="closeLightbox()"
                >
                    {{-- Hero image with overlay nav --}}
                    <div class="relative aspect-[16/10] bg-toco-silver-2 group overflow-hidden">
                        <div
                            class="absolute inset-0 flex will-change-transform"
                            :style="{
                                transform: `translate3d(calc(${-index * 100}% + ${heroDragOffsetPx}px), 0, 0)`,
                                transition: heroDragging ? 'none' : 'transform 350ms cubic-bezier(0.22, 1, 0.36, 1)',
                            }"
                        >
                            @foreach ($photoUrls as $i => $url)
                                <img
                                    src="{{ $url }}"
                                    alt="{{ $vehicle->title }}"
                                    class="w-full h-full object-cover shrink-0 cursor-zoom-in select-none"
                                    style="touch-action: pan-y;"
                                    {{ $i === 0 ? '' : 'loading=lazy' }}
                                    draggable="false"
                                    @click="maybeOpenLightbox()"
                                    @pointerdown="heroSwipeStart($event)"
                                    @pointermove="heroSwipeMove($event)"
                                    @pointerup="heroSwipeEnd($event)"
                                    @pointercancel="heroSwipeEnd($event)"
                                    @dragstart.prevent
                                >
                            @endforeach
                        </div>

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

                        @if ($videoUrl)
                            {{-- Play button — shown over the first photo only. --}}
                            <button
                                type="button"
                                x-show="index === 0"
                                x-cloak
                                @click.stop="$dispatch('open-video')"
                                aria-label="Play walkaround video"
                                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 rounded-full bg-toco-red/90 hover:bg-toco-red text-white grid place-items-center shadow-lg ring-4 ring-white/30 transition"
                            >
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            </button>
                        @endif
                    </div>

                    @if (count($photoUrls) > 1)
                        <div class="grid grid-cols-6 gap-1 p-1 border-t border-line">
                            @foreach ($thumbUrls as $i => $url)
                                <button
                                    type="button"
                                    @click="goTo({{ $i }})"
                                    :class="index === {{ $i }} ? 'border-toco-red' : 'border-transparent hover:border-toco-red'"
                                    class="relative aspect-[4/3] bg-toco-silver-2 overflow-hidden border-2 transition"
                                >
                                    <img src="{{ $url }}" alt="" width="300" height="225" loading="lazy" class="w-full h-full object-cover">
                                    @if ($i === 0 && $videoUrl)
                                        <span
                                            @click.stop="$dispatch('open-video')"
                                            role="button"
                                            aria-label="Play walkaround video"
                                            class="absolute inset-0 grid place-items-center bg-black/15 hover:bg-black/30 cursor-pointer transition"
                                        >
                                            <span class="w-7 h-7 rounded-full bg-toco-red/90 text-white grid place-items-center shadow">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                            </span>
                                        </span>
                                    @endif
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
                            :src="fullPhotos[index]"
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

                @if ($videoUrl)
                    {{-- Walkaround video modal --}}
                    <div
                        x-data="{ open: false }"
                        @open-video.window="open = true; $nextTick(() => { $refs.video.currentTime = 0; $refs.video.play().catch(() => {}); })"
                        @keydown.escape.window="open && (open = false, $refs.video.pause())"
                        x-show="open"
                        x-cloak
                        @click.self="open = false; $refs.video.pause()"
                        x-effect="document.body.style.overflow = open ? 'hidden' : ''"
                        class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
                        style="display: none;"
                    >
                        <button
                            type="button"
                            @click="open = false; $refs.video.pause()"
                            aria-label="Close video"
                            class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center z-10"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                        <video
                            x-ref="video"
                            src="{{ $videoUrl }}"
                            controls
                            playsinline
                            preload="metadata"
                            class="max-w-full max-h-[85vh] rounded-sm shadow-2xl bg-black"
                            @click.stop
                        ></video>
                    </div>
                @endif

                {{-- Description --}}
                @if ($vehicle->description)
                    <div class="bg-white border border-line rounded-sm p-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">About this vehicle</p>
                        <h2 class="font-bold text-toco-navy text-lg mt-1 mb-3">Description</h2>
                        <div class="prose prose-sm max-w-none text-ink-soft leading-relaxed">{!! nl2br(e($vehicle->description)) !!}</div>
                    </div>
                @endif

                {{-- Vehicle Options --}}
                @if (! empty($vehicle->features))
                    @php($featureSchema = config('vehicle_features'))
                    <div class="bg-white border border-line rounded-sm p-5">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Equipment</p>
                        <h2 class="font-bold text-toco-navy text-lg mt-1 mb-3">Vehicle Options</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                            @foreach ($vehicle->features as $group => $items)
                                @if (is_array($items) && count($items) > 0)
                                    <div>
                                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-2">{{ $featureSchema[$group]['label'] ?? str_replace('_', ' ', \Illuminate\Support\Str::title((string) $group)) }}</p>
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
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">{{ $vehicle->stock_no ? 'Stock #'.$vehicle->stock_no : $vehicle->ref_no }}</p>
                        <p class="text-xl font-extrabold text-toco-navy leading-tight mt-1 pr-24">{{ $vehicle->title }}</p>
                        <p class="font-mono text-[11px] uppercase tracking-widest text-ink-soft mt-2">FOB</p>
                        @if ($vehicle->price_on_request)
                            <p class="font-extrabold text-3xl text-toco-red mt-1">On request</p>
                        @elseif ($vehicle->isDiscounted())
                            <div class="flex items-baseline gap-3 mt-1">
                                <p class="font-extrabold text-3xl text-toco-red">@money($vehicle->price_fob_discount)</p>
                                <p class="font-mono text-base text-ink-soft line-through">@money($vehicle->price_fob)</p>
                                <span class="bg-toco-red text-white font-bold uppercase tracking-widest text-[10px] px-2 py-1 rounded-sm">Save {{ (int) round((((float) $vehicle->price_fob - (float) $vehicle->price_fob_discount) / (float) $vehicle->price_fob) * 100) }}%</span>
                            </div>
                        @else
                            <p class="font-extrabold text-3xl text-toco-red mt-1">@money($vehicle->price_fob)</p>
                        @endif
                        @if (! $vehicle->price_on_request && $vehicle->price_fob > 0 && ($destPort ?? null) && $vehicle->m3 > 0)
                            <p class="text-[12px] text-ink-soft mt-2 leading-tight">
                                CIF to <span class="font-semibold text-toco-navy">{{ $destPort->name }}</span>:
                                <span class="font-bold text-toco-navy">@cif($vehicle, $destPort)</span>
                            </p>
                        @endif
                    </div>
                    <div class="p-5 space-y-2">
                        @if ($isSold)
                            <div class="w-full text-center bg-toco-red text-white border border-toco-red font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
                                Sold — view our available stock <a href="{{ route('vehicles.index') }}" class="underline">here</a>
                            </div>
                        @else
                        @auth
                            @if ($buyable && $paypalReady)
                                <form method="POST" action="{{ route('checkout.start', $vehicle->slug) }}">
                                    @csrf
                                    <button type="submit" class="w-full text-center bg-toco-navy hover:bg-toco-navy-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm inline-flex items-center justify-center gap-2">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H7z"/></svg>
                                        Buy with PayPal — @money($vehicle->effectivePriceFob() ?? $vehicle->price_fob)
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
                        @endif
                    </div>
                </div>

                {{-- CIF estimator with add-ons + option upsells --}}
                @unless ($vehicle->price_on_request || $vehicle->m3 === null || (float) $vehicle->m3 === 0.0)
                    <div class="bg-white border border-line rounded-sm p-5"
                        x-data="cifCalc({
                            countries: {{ collect($countriesData)->toJson() }},
                            preCountryId: '{{ $destPort?->country_id }}',
                            prePortId: '{{ $destPort?->id }}',
                            slug: '{{ $vehicle->slug }}',
                            options: {{ $vehicleOptions->map(fn ($o) => ['id' => (int) $o->id, 'name' => $o->name, 'price' => $o->price === null ? null : (float) $o->price, 'tooltip' => $o->tooltip])->toJson() }},
                            marineInsuranceFee: {{ (float) $cifSettings->marine_insurance_usd }},
                            maintenanceFee: {{ (float) $cifSettings->maintenance_package_usd }},
                            preInspectionFee: {{ (float) $cifSettings->pre_inspection_fee_usd }},
                        })">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Calculate Your Total Price</p>
                        <h3 class="font-bold text-toco-navy text-base mb-3">Estimate landed cost</h3>

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
                                    <option :value="p.id" x-text="p.name"></option>
                                </template>
                            </select>
                            <button type="button" @click="submit()" :disabled="loading || !portId" class="w-full bg-toco-navy hover:bg-toco-navy-deep disabled:opacity-50 text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">
                                <span x-show="!loading">Calculate</span>
                                <span x-show="loading" x-cloak>Calculating…</span>
                            </button>
                        </div>

                        {{-- Import regulations for the chosen port --}}
                        <div x-show="regulation()" x-cloak class="mt-3 border border-line rounded-sm bg-toco-silver-2/60 p-3 text-[12px] leading-snug">
                            <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-1.5">Import rules — your port</p>
                            <p x-show="regulation()?.description" x-text="regulation()?.description" class="text-ink mb-2"></p>
                            <dl class="space-y-1">
                                <div x-show="regulation()?.age_limit" class="flex gap-2"><dt class="text-ink-soft w-28 shrink-0">Age limit</dt><dd class="font-semibold text-toco-navy" x-text="regulation()?.age_limit"></dd></div>
                                <div x-show="regulation()?.shipment_time" class="flex gap-2"><dt class="text-ink-soft w-28 shrink-0">Shipment time</dt><dd class="font-semibold text-toco-navy" x-text="regulation()?.shipment_time"></dd></div>
                                <div x-show="regulation()?.notes" class="flex gap-2"><dt class="text-ink-soft w-28 shrink-0">Notes</dt><dd class="text-ink whitespace-pre-line" x-text="regulation()?.notes"></dd></div>
                            </dl>
                        </div>

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
                                <label class="flex items-center justify-between gap-2 cursor-pointer">
                                    <span class="inline-flex items-center gap-2">
                                        <input type="checkbox" x-model="preInspection" class="text-toco-red">
                                        <span class="font-semibold text-ink">Pre-inspection Fee</span>
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
                    </div>
                @endunless

                {{-- ============ Vehicle Details (right sidebar, 2 sub-columns per PDF) ============ --}}
                <div class="bg-white border border-line rounded-sm p-5">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">At a glance</p>
                    <h2 class="font-bold text-toco-navy text-lg mt-1 mb-3">Vehicle Details</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-0 text-[13px]">
                        <dl class="divide-y divide-line">
                            @foreach ($detailsLeft as $row)
                                <div class="py-1.5">
                                    <dt class="font-mono text-[9px] uppercase tracking-widest text-ink-soft leading-tight">{{ $row[0] }}</dt>
                                    <dd class="font-semibold text-ink break-words leading-snug mt-0.5">{{ $row[1] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <dl class="divide-y divide-line">
                            @foreach ($detailsRight as $row)
                                <div class="py-1.5">
                                    <dt class="font-mono text-[9px] uppercase tracking-widest text-ink-soft leading-tight">{{ $row[0] }}</dt>
                                    <dd class="font-semibold text-ink break-words leading-snug mt-0.5">{{ $row[1] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </aside>
        </div>

        @auth
        @if (! $isSold)
            {{-- Request-a-quote form --}}
            <div id="quote-form" class="bg-white border border-line border-t-4 border-t-toco-red rounded-sm p-6 mt-8 max-w-3xl"
                x-data="{
                    countryId: '', portId: '', ports: [],
                    countries: @js($countriesData),
                    init() {
                        // Pre-fill from the destination saved earlier (toco_port cookie).
                        // Values are applied via $nextTick so the <option> x-for
                        // templates have rendered before the <select>s sync.
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
                    regulation() {
                        const p = this.ports.find(p => p.id == this.portId);
                        return p && p.regulation ? p.regulation : null;
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

                    {{-- Import regulations for the chosen port --}}
                    <div x-show="regulation()" x-cloak class="md:col-span-2 border border-line rounded-sm bg-toco-silver-2/60 p-4">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-2">Import regulations — your destination port</p>
                        <p x-show="regulation()?.description" x-text="regulation()?.description" class="text-[13px] text-ink mb-3"></p>
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[13px]">
                            <div x-show="regulation()?.age_limit">
                                <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest">Age limit</dt>
                                <dd class="font-semibold text-toco-navy mt-0.5" x-text="regulation()?.age_limit"></dd>
                            </div>
                            <div x-show="regulation()?.shipment_time">
                                <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest">Shipment time</dt>
                                <dd class="font-semibold text-toco-navy mt-0.5" x-text="regulation()?.shipment_time"></dd>
                            </div>
                            <div x-show="regulation()?.notes" class="sm:col-span-3">
                                <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest">Notes</dt>
                                <dd class="text-ink mt-0.5 whitespace-pre-line" x-text="regulation()?.notes"></dd>
                            </div>
                        </dl>
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
        @endif
        @endauth
    </section>

    {{-- ============ Related vehicles ============ --}}
    @if (! empty($relatedVehicles) && $relatedVehicles->count() > 0)
        <section class="bg-toco-silver-2 mt-10 border-t border-line">
            <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-10 md:py-14">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-6">
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">You might also like</p>
                        <h2 class="text-2xl md:text-[28px] font-extrabold text-toco-navy mt-1 leading-tight">Related vehicles</h2>
                        <p class="text-ink-soft text-sm mt-1">More {{ $vehicle->make?->name ?? '' }} {{ $vehicle->vehicleModel?->name ?? ($vehicle->bodyType?->name ? mb_strtolower($vehicle->bodyType->name).'s' : 'vehicles') }} in our stock.</p>
                    </div>
                    <a href="{{ route('vehicles.index').'?'.http_build_query(array_filter(['make' => $vehicle->make?->slug, 'vehicle_model' => $vehicle->vehicleModel?->slug])) }}" class="text-sm font-bold text-toco-red hover:text-toco-red-deep inline-flex items-center gap-1">
                        See all <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
                    </a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach ($relatedVehicles as $rv)
                        <x-vehicle-card :vehicle="$rv" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.site>
