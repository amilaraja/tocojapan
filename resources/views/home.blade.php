@php
    $showStockCounts = app(\App\Settings\GeneralSettings::class)->show_stock_counts;
    $title = 'Used Japanese cars for export — Toco Japan';
    $description = ($totalPublished ?? 0) > 0
        ? number_format($totalPublished).' used Japanese vehicles ready to export — RHD/LHD, RoRo & container shipping worldwide. FOB Yokohama from $1,500. CIF quote to your port in minutes.'
        : 'Used Japanese vehicles for export to your country — RHD/LHD, RoRo & container shipping worldwide. FOB Yokohama, transparent CIF quotes to your port.';

    // Defaults — used whenever the CMS data hasn't been set yet (or falls
    // back to the v5 sample content for a freshly-seeded site).
    $promoLeftDefault = [
        ['tone' => 'red',    'title' => 'Kei trucks', 'sub' => '660cc · RHD',     'url' => '#'],
        ['tone' => 'navy',   'title' => 'Import regulations', 'sub' => 'Per country', 'url' => '#'],
        ['tone' => 'silver', 'title' => 'Create account', 'sub' => 'Save & request', 'url' => '#'],
    ];
    $promoRightDefault = [
        ['tone' => 'navy',   'title' => 'Auction agent', 'sub' => '69,000+ cars',  'url' => '#'],
        ['tone' => 'red',    'title' => 'Shipping & inspection', 'sub' => 'JEVIC · JAAI', 'url' => '#'],
        ['tone' => 'silver', 'title' => 'Banking', 'sub' => 'Telegraphic transfer', 'url' => '#'],
    ];
    $heroSlidesDefault = [
        ['image' => '/img/v5/hero-1.jpg', 'alt' => ''],
        ['image' => '/img/v5/hero-2.jpg', 'alt' => ''],
        ['image' => '/img/v5/hero-3.jpeg', 'alt' => ''],
    ];

    $promoLeft = $content['promo_left'] ?? $promoLeftDefault;
    $promoRight = $content['promo_right'] ?? $promoRightDefault;
    $heroSlides = collect($content['hero_slides'] ?? $heroSlidesDefault)->map(function ($s) {
        // Filament FileUpload stores a relative path like "home/hero/abc.jpg" —
        // prefix /storage/ so it resolves through the public-storage symlink.
        $img = $s['image'] ?? '';
        if ($img !== '' && ! str_starts_with($img, '/') && ! str_starts_with($img, 'http')) {
            $s['image'] = '/storage/'.$img;
        }
        return $s;
    })->all();
@endphp

<x-layouts.site :title="$title" :description="$description">
    @push('head')
        {{-- Preload the first hero slide — it is the LCP element, so start its
             download before CSS/JS parse to cut Largest Contentful Paint time. --}}
        @if (! empty($heroSlides[0]['image'] ?? null))
            <link rel="preload" as="image" href="{{ $heroSlides[0]['image'] }}" fetchpriority="high">
        @endif
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'AutoDealer',
            'name' => config('app.name', 'Toco Japan'),
            'url' => url('/'),
            'logo' => asset('img/footer-logos/toco.png'),
            'description' => 'Japanese used-car exporter. Yokohama-based, shipping worldwide since 2009.',
            'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'JP', 'addressLocality' => 'Yokohama'],
            'sameAs' => array_values(array_filter([
                app(\App\Settings\SocialSettings::class)->facebook_page_id ? 'https://facebook.com/'.app(\App\Settings\SocialSettings::class)->facebook_page_id : null,
            ])),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <h1 class="sr-only">Toco Japan — Used Japanese Vehicles for Export Worldwide</h1>

    {{-- Hero band --}}
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white py-6">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)_220px] gap-4">
                {{-- Left promo tiles --}}
                <div class="hidden lg:flex flex-col gap-3">
                    @foreach ($promoLeft as $tile)
                        @php
                            $img = $tile['image'] ?? '';
                            if ($img !== '' && ! str_starts_with($img, '/') && ! str_starts_with($img, 'http')) {
                                $img = '/storage/'.$img;
                            }
                        @endphp
                        @if ($img !== '')
                            <a href="{{ $tile['url'] ?? '#' }}" title="{{ $tile['title'] ?? '' }}" class="block rounded-sm overflow-hidden border border-line hover:border-toco-red hover:-translate-x-[2px] transition">
                                <img src="{{ $img }}" alt="{{ $tile['title'] ?? '' }}" class="w-full h-auto block" loading="lazy">
                            </a>
                        @endif
                    @endforeach
                </div>

                {{-- Center carousel --}}
                <div class="min-w-0"
                    x-data="{ idx: 0, slides: @js(array_map(fn($s) => $s['image'] ?? '', $heroSlides)), next() { this.idx = (this.idx + 1) % this.slides.length }, prev() { this.idx = (this.idx - 1 + this.slides.length) % this.slides.length } }"
                    x-init="setInterval(() => next(), 6000)"
                >
                    {{-- Seasonal strip — image-only, links to the CTA URL. Sits above the slider. --}}
                    @if (! empty($content['seasonal']['enabled'] ?? true))
                        @php
                            $sx = $content['seasonal'] ?? [];
                            $sxImg = $sx['image'] ?? '/img/v5/seasonal-banner.jpg';
                            $sxUrl = $sx['cta_url'] ?? route('vehicles.index');
                            $sxAlt = $sx['tag'] ?? 'Seasonal promotion';
                            // FileUpload paths are relative — prefix /storage/ if so.
                            if ($sxImg !== '' && ! str_starts_with($sxImg, '/') && ! str_starts_with($sxImg, 'http')) {
                                $sxImg = '/storage/'.$sxImg;
                            }
                        @endphp
                        <a href="{{ $sxUrl }}" aria-label="{{ $sxAlt }}"
                           class="mb-3 block overflow-hidden border border-white/10 rounded-sm">
                            <img src="{{ $sxImg }}" alt="{{ $sxAlt }}" width="1280" height="68" decoding="async" class="block w-full h-auto">
                        </a>
                    @endif

                    {{-- Slider auto-sizes to the natural image height. The
                         first image (rendered statically, opacity-0 if not
                         active) sets the box height; the others overlay
                         absolutely and cross-fade. Hero banners are 1500x250
                         (6:1) but this works for any matching-ratio set. --}}
                    <div class="relative bg-toco-silver border border-white/10 overflow-hidden">
                        {{-- Slide 0 is rendered statically with a real src so it is
                             present in the initial HTML: the browser's preload
                             scanner can fetch it immediately and Lighthouse can
                             detect it as the LCP element (the old all-in-x-for
                             markup left the hero empty until Alpine booted →
                             NO_LCP). It stays in-flow to set the box height;
                             Alpine only toggles its opacity for the cross-fade. --}}
                        <img src="{{ $heroSlides[0]['image'] ?? '' }}" alt="" width="1500" height="250"
                             fetchpriority="high" decoding="async"
                             class="block w-full h-auto relative transition-opacity duration-700"
                             :class="idx === 0 ? 'opacity-100' : 'opacity-0'">
                        {{-- Remaining slides are absolute cross-fade overlays, hydrated by Alpine. --}}
                        <template x-for="(slide, i) in slides.slice(1)" :key="slide">
                            <img :src="slide" alt="" width="1500" height="250" decoding="async" loading="lazy"
                                 class="block w-full h-full object-cover absolute inset-0 pointer-events-none transition-opacity duration-700"
                                 :class="(i + 1) === idx ? 'opacity-100' : 'opacity-0'">
                        </template>
                        <button type="button" @click="prev" class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 grid place-items-center bg-white/85 hover:bg-white text-toco-navy rounded-sm" aria-label="Previous">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m15 6-6 6 6 6"/></svg>
                        </button>
                        <button type="button" @click="next" class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 grid place-items-center bg-white/85 hover:bg-white text-toco-navy rounded-sm" aria-label="Next">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
                        </button>
                        <div class="absolute bottom-3 right-3 flex gap-1.5">
                            <template x-for="(slide, i) in slides" :key="'dot-'+slide">
                                <button type="button" @click="idx = i" class="w-6 h-1 rounded-sm transition" :class="i === idx ? 'bg-toco-red' : 'bg-white/50 hover:bg-white/80'" aria-label="Go to slide"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Search panel --}}
                    <div class="bg-white text-ink border border-line rounded-sm shadow-[0_10px_30px_rgba(16,20,58,.18)] p-4 md:p-5 mt-3"
                        x-data="{
                            tab: 'make',
                            makeSlug: '', modelSlug: '', yearFrom: '', priceTo: '', transmission: '', bodyType: '', stockRef: '',
                            models: [], loadingModels: false,
                            matchCount: {{ (int) $totalPublished }},
                            countLoading: false,
                            recountDebounce: null,
                            init() {
                                this.$watch('makeSlug',     () => this.queueRecount());
                                this.$watch('modelSlug',    () => this.queueRecount());
                                this.$watch('yearFrom',     () => this.queueRecount());
                                this.$watch('priceTo',      () => this.queueRecount());
                                this.$watch('transmission', () => this.queueRecount());
                                this.$watch('bodyType',     () => this.queueRecount());
                            },
                            queueRecount() {
                                clearTimeout(this.recountDebounce);
                                this.recountDebounce = setTimeout(() => this.recount(), 220);
                            },
                            async recount() {
                                const p = this.filterParams();
                                this.countLoading = true;
                                try {
                                    const r = await fetch('/api/v1/vehicles/count?' + p.toString(), { headers: { Accept: 'application/json' } });
                                    const j = await r.json();
                                    this.matchCount = j.data?.count ?? this.matchCount;
                                } finally { this.countLoading = false; }
                            },
                            filterParams() {
                                const p = new URLSearchParams();
                                if (this.makeSlug)     p.set('make', this.makeSlug);
                                if (this.modelSlug)    p.set('vehicle_model', this.modelSlug);
                                if (this.yearFrom)     p.set('year_from', this.yearFrom);
                                if (this.priceTo)      p.set('price_to', this.priceTo);
                                if (this.transmission) p.set('transmission', this.transmission);
                                if (this.bodyType)     p.set('body_type', this.bodyType);
                                return p;
                            },
                            async loadModels(slug) {
                                this.modelSlug = ''; this.models = [];
                                if (!slug) return;
                                this.loadingModels = true;
                                try {
                                    const r = await fetch(`/api/v1/makes/${slug}/models`, { headers: { Accept: 'application/json' } });
                                    const j = await r.json();
                                    this.models = j.data || [];
                                } finally { this.loadingModels = false; }
                            },
                            submit() {
                                const p = new URLSearchParams();
                                if (this.tab === 'make') {
                                    if (this.makeSlug) p.set('make', this.makeSlug);
                                    if (this.modelSlug) p.set('vehicle_model', this.modelSlug);
                                    if (this.yearFrom) p.set('year_from', this.yearFrom);
                                    if (this.priceTo) p.set('price_to', this.priceTo);
                                    if (this.transmission) p.set('transmission', this.transmission);
                                } else if (this.tab === 'body') {
                                    if (this.bodyType) p.set('body_type', this.bodyType);
                                } else if (this.tab === 'budget') {
                                    if (this.priceTo) p.set('price_to', this.priceTo);
                                } else if (this.tab === 'ref') {
                                    if (this.stockRef) p.set('q', this.stockRef);
                                }
                                window.location = '{{ route('vehicles.index') }}?' + p.toString();
                            }
                        }"
                    >
                        <div class="flex flex-wrap gap-1 border-b border-line -mt-1 mb-3">
                            @foreach (['make' => 'Make & Model', 'body' => 'Body', 'budget' => 'Budget', 'ref' => 'Stock ID/Ref'] as $key => $label)
                                <button type="button" @click="tab = '{{ $key }}'"
                                    :class="tab === '{{ $key }}' ? 'text-toco-red border-toco-red' : 'text-ink-soft border-transparent hover:text-ink'"
                                    class="text-[11px] font-bold uppercase tracking-widest px-2.5 py-2 border-b-2 -mb-px transition">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <form @submit.prevent="submit()" x-show="tab === 'make'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
                            <select x-model="makeSlug" @change="loadModels(makeSlug)" aria-label="Make" class="w-full text-sm">
                                <option value="">Any make</option>
                                @foreach ($allMakes as $m)
                                    <option value="{{ $m->slug }}" {{ ($m->published_count ?? 0) === 0 ? 'disabled' : '' }}>{{ $m->name }}@if ($showStockCounts) ({{ $m->published_count ?? 0 }})@endif</option>
                                @endforeach
                            </select>
                            <select x-model="modelSlug" :disabled="loadingModels || !makeSlug" aria-label="Model" class="w-full text-sm disabled:bg-toco-silver-2">
                                <option value="">Any model</option>
                                <template x-for="m in models" :key="m.slug">
                                    <option :value="m.slug" x-text="m.name"></option>
                                </template>
                            </select>
                            <select x-model="yearFrom" aria-label="Year from" class="w-full text-sm">
                                <option value="">Any year</option>
                                @for ($y = (int) date('Y'); $y >= 1990; $y--)<option value="{{ $y }}">{{ $y }}+</option>@endfor
                            </select>
                            <select x-model="priceTo" aria-label="Maximum price" class="w-full text-sm">
                                <option value="">Any price</option>
                                @foreach ([3000, 5000, 8000, 12000, 20000, 35000, 60000] as $p)<option value="{{ $p }}">≤ ${{ number_format($p) }}</option>@endforeach
                            </select>
                            <button type="submit" :disabled="matchCount === 0" class="col-span-2 md:col-span-2 lg:col-span-1 bg-toco-red hover:bg-toco-red-deep disabled:bg-toco-silver-2 disabled:text-ink-soft disabled:cursor-not-allowed text-white font-bold uppercase tracking-widest text-xs px-3 py-2.5 rounded-sm inline-flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                <span class="lg:hidden" x-text="countLoading ? 'Counting…' : ('Search ' + matchCount.toLocaleString() + ' vehicles')"></span>
                                <span class="hidden lg:inline" x-text="countLoading ? '…' : ('Search · ' + matchCount.toLocaleString())"></span>
                            </button>
                        </form>

                        <form @submit.prevent="submit()" x-show="tab === 'body'" x-cloak class="grid grid-cols-3 gap-2">
                            @foreach ($allBodyTypes as $bt)
                                <button type="button" @click="bodyType = '{{ $bt->slug }}'; submit()" {{ ($bt->published_count ?? 0) === 0 ? 'disabled' : '' }} class="border border-line hover:border-toco-navy hover:bg-toco-silver-2 disabled:opacity-40 disabled:cursor-not-allowed px-2 py-2 text-[11px] font-semibold text-ink rounded-sm flex flex-col items-center gap-1">
                                    @if ($bt->getLogoUrl())
                                        <img src="{{ $bt->getLogoUrl() }}" alt="" class="h-7 w-auto" loading="lazy">
                                    @endif
                                    <span>{{ $bt->name }}@if ($showStockCounts) ({{ $bt->published_count ?? 0 }})@endif</span>
                                </button>
                            @endforeach
                        </form>

                        <form @submit.prevent="submit()" x-show="tab === 'budget'" x-cloak class="grid grid-cols-2 gap-2">
                            @foreach ([3000, 5000, 8000, 12000, 20000, 35000, 60000] as $p)
                                <button type="button" @click="priceTo = {{ $p }}; submit()" class="border border-line hover:border-toco-navy hover:bg-toco-silver-2 px-2 py-2 text-[12px] font-semibold text-ink rounded-sm">
                                    ≤ ${{ number_format($p) }}
                                </button>
                            @endforeach
                        </form>

                        <form @submit.prevent="submit()" x-show="tab === 'ref'" x-cloak class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Search by Stock ID or reference number</label>
                            <div class="flex gap-2">
                                <input x-model="stockRef" type="text" aria-label="Stock ID or reference number" placeholder="e.g. E01888 or HA4-236…" class="flex-1 text-sm">
                                <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-3 py-2 rounded-sm">Find</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Right promo tiles --}}
                <div class="hidden lg:flex flex-col gap-3">
                    @foreach ($promoRight as $tile)
                        @php
                            $img = $tile['image'] ?? '';
                            if ($img !== '' && ! str_starts_with($img, '/') && ! str_starts_with($img, 'http')) {
                                $img = '/storage/'.$img;
                            }
                        @endphp
                        @if ($img !== '')
                            <a href="{{ $tile['url'] ?? '#' }}" title="{{ $tile['title'] ?? '' }}" class="block rounded-sm overflow-hidden border border-line hover:border-toco-red hover:translate-x-[2px] transition">
                                <img src="{{ $img }}" alt="{{ $tile['title'] ?? '' }}" class="w-full h-auto block" loading="lazy">
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </section>


    {{-- Featured grid with browse-by-make and browse-by-body-type sidebars --}}
    @include('partials.home-featured', ['featured' => $featured, 'makesWithCounts' => $makesWithCounts, 'bodyTypesWithCounts' => $bodyTypesWithCounts, 'totalPublished' => $totalPublished])

    {{-- Why Toco --}}
    @include('partials.home-why', ['content' => $content])

    {{-- Stats — "By the numbers, since 2009." (toggleable; on by default) --}}
    @if (($content['stats']['enabled'] ?? true))
        @include('partials.home-stats', ['content' => $content])
    @endif

    {{-- Customer testimonials — 6-column compact grid --}}
    @include('partials.home-testimonials', ['content' => $content])

    {{-- How it works --}}
    @include('partials.home-how', ['content' => $content])

    {{-- Buyer FAQ (with FAQPage JSON-LD for rich results) --}}
    @include('partials.home-faq', ['content' => $content])

    {{-- Inquiry / Subscribe CTA strip --}}
    @include('partials.home-cta-strip')
</x-layouts.site>
