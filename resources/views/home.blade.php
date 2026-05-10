@php
    $title = 'Toco Japan — Japanese cars, delivered worldwide';

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

<x-layouts.site :title="$title">
    {{-- Hero band --}}
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white pt-4 pb-24">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)_220px] gap-4">
                {{-- Left promo tiles --}}
                <div class="hidden lg:flex flex-col gap-3">
                    @foreach ($promoLeft as $tile)
                        @php
                            $iconBg = match($tile['tone'] ?? 'navy') { 'red' => 'bg-toco-red text-white', 'navy' => 'bg-toco-navy text-white', default => 'bg-toco-silver text-toco-navy' };
                        @endphp
                        <a href="{{ $tile['url'] ?? '#' }}" class="bg-white text-ink rounded-sm border border-line hover:border-ink hover:translate-x-[-2px] transition flex items-center gap-3 px-3.5 py-3 min-h-[76px]">
                            <span class="w-9 h-9 grid place-items-center {{ $iconBg }} rounded-sm shrink-0">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-bold text-[13px] leading-tight">{{ $tile['title'] ?? '' }}</span>
                                <span class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mt-0.5">{{ $tile['sub'] ?? '' }}</span>
                            </span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-ink-soft shrink-0"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    @endforeach
                </div>

                {{-- Center carousel --}}
                <div class="min-w-0"
                    x-data="{ idx: 0, slides: @js(array_map(fn($s) => $s['image'] ?? '', $heroSlides)), next() { this.idx = (this.idx + 1) % this.slides.length }, prev() { this.idx = (this.idx - 1 + this.slides.length) % this.slides.length } }"
                    x-init="setInterval(() => next(), 6000)"
                >
                    {{-- Slider auto-sizes to the natural image height. The
                         first image (rendered statically, opacity-0 if not
                         active) sets the box height; the others overlay
                         absolutely and cross-fade. Hero banners are 1500x250
                         (6:1) but this works for any matching-ratio set. --}}
                    <div class="relative bg-toco-silver border border-white/10 overflow-hidden">
                        <template x-for="(slide, i) in slides" :key="slide">
                            <img :src="slide" alt=""
                                 class="block w-full h-auto transition-opacity duration-700"
                                 :class="{
                                     'opacity-100 relative': i === idx,
                                     'opacity-0 absolute inset-0 h-full object-cover pointer-events-none': i !== idx
                                 }">
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

                    {{-- Seasonal strip — image-only, links to the CTA URL. --}}
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
                           class="mt-3 block overflow-hidden border border-white/10 rounded-sm">
                            <img src="{{ $sxImg }}" alt="{{ $sxAlt }}" class="block w-full h-auto">
                        </a>
                    @endif
                </div>

                {{-- Right promo tiles --}}
                <div class="hidden lg:flex flex-col gap-3">
                    @foreach ($promoRight as $tile)
                        @php
                            $iconBg = match($tile['tone'] ?? 'navy') { 'red' => 'bg-toco-red text-white', 'navy' => 'bg-toco-navy text-white', default => 'bg-toco-silver text-toco-navy' };
                        @endphp
                        <a href="{{ $tile['url'] ?? '#' }}" class="bg-white text-ink rounded-sm border border-line hover:border-ink hover:translate-x-[2px] transition flex items-center gap-3 px-3.5 py-3 min-h-[76px]">
                            <span class="w-9 h-9 grid place-items-center {{ $iconBg }} rounded-sm shrink-0">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-bold text-[13px] leading-tight">{{ $tile['title'] ?? '' }}</span>
                                <span class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mt-0.5">{{ $tile['sub'] ?? '' }}</span>
                            </span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-ink-soft shrink-0"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Search panel — overlaps hero with negative margin --}}
    <section class="relative -mt-14">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8">
            <div class="bg-white border border-line shadow-[0_10px_30px_rgba(16,20,58,.08)] p-5 md:p-6"
                x-data="{
                    tab: 'make',
                    makeSlug: '', modelSlug: '', yearFrom: '', priceTo: '', transmission: '', bodyType: '', stockRef: '',
                    models: [], loadingModels: false,
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
                        window.location = '/vehicles?' + p.toString();
                    }
                }"
            >
                {{-- Tabs --}}
                <div class="flex flex-wrap gap-1 border-b border-line -mt-1 mb-4">
                    @foreach (['make' => 'By Make & Model', 'body' => 'By Body Type', 'budget' => 'By Budget', 'ref' => 'Stock Reference'] as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'text-toco-red border-toco-red' : 'text-ink-soft border-transparent hover:text-ink'"
                            class="text-[12px] font-bold uppercase tracking-widest px-3 py-2.5 border-b-2 -mb-px transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- By Make & Model --}}
                <form @submit.prevent="submit()" x-show="tab === 'make'" class="grid grid-cols-1 md:grid-cols-6 gap-2">
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Make</label>
                        <select x-model="makeSlug" @change="loadModels(makeSlug)" class="w-full border-line text-sm rounded-sm">
                            <option value="">Any make</option>
                            @foreach ($allMakes as $m)
                                <option value="{{ $m->slug }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Model</label>
                        <select x-model="modelSlug" :disabled="loadingModels || !makeSlug" class="w-full border-line text-sm rounded-sm disabled:bg-toco-silver-2">
                            <option value="">Any model</option>
                            <template x-for="m in models" :key="m.slug">
                                <option :value="m.slug" x-text="m.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Year from</label>
                        <select x-model="yearFrom" class="w-full border-line text-sm rounded-sm">
                            <option value="">Any</option>
                            @for ($y = (int) date('Y'); $y >= 1990; $y--)<option value="{{ $y }}">{{ $y }}</option>@endfor
                        </select>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Price USD</label>
                        <select x-model="priceTo" class="w-full border-line text-sm rounded-sm">
                            <option value="">Any</option>
                            @foreach ([3000, 5000, 8000, 12000, 20000, 35000, 60000] as $p)<option value="{{ $p }}">Up to ${{ number_format($p) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Transmission</label>
                        <select x-model="transmission" class="w-full border-line text-sm rounded-sm">
                            <option value="">Any</option>
                            <option value="automatic">Automatic</option>
                            <option value="manual">Manual</option>
                            <option value="cvt">CVT</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm inline-flex items-center justify-center gap-2 mt-auto">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        Search
                    </button>
                </form>

                {{-- By Body Type --}}
                <form @submit.prevent="submit()" x-show="tab === 'body'" x-cloak class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                    @foreach ($allBodyTypes as $bt)
                        <button type="button" @click="bodyType = '{{ $bt->slug }}'; submit()" class="border border-line hover:border-toco-navy hover:bg-toco-silver-2 px-3 py-3 text-[12px] font-semibold text-ink rounded-sm">
                            {{ $bt->name }}
                        </button>
                    @endforeach
                </form>

                {{-- By Budget --}}
                <form @submit.prevent="submit()" x-show="tab === 'budget'" x-cloak class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                    @foreach ([3000, 5000, 8000, 12000, 20000, 35000, 60000] as $p)
                        <button type="button" @click="priceTo = {{ $p }}; submit()" class="border border-line hover:border-toco-navy hover:bg-toco-silver-2 px-3 py-3 text-[12px] font-semibold text-ink rounded-sm">
                            Up to ${{ number_format($p) }}
                        </button>
                    @endforeach
                </form>

                {{-- Stock Reference --}}
                <form @submit.prevent="submit()" x-show="tab === 'ref'" x-cloak class="flex gap-2">
                    <input x-model="stockRef" type="text" placeholder="e.g. TJ-LUY908" class="flex-1 border-line text-sm rounded-sm">
                    <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">Find</button>
                </form>

                {{-- Popular chips --}}
                @php
                    $popularChips = $content['popular_chips'] ?? [
                        ['label' => 'Hilux Surf',          'query_string' => '?make=toyota&q=Hilux'],
                        ['label' => 'Land Cruiser Prado',  'query_string' => '?make=toyota&vehicle_model=land-cruiser-prado'],
                        ['label' => 'Alphard Hybrid',      'query_string' => '?make=toyota&vehicle_model=alphard&fuel=hybrid'],
                        ['label' => 'Kei trucks',          'query_string' => '?body_type=mini-truck'],
                        ['label' => 'RHD SUVs',            'query_string' => '?body_type=suv&steering=right'],
                        ['label' => 'Under $5,000',        'query_string' => '?price_to=5000'],
                    ];
                @endphp
                <div class="mt-5 pt-4 border-t border-line flex items-center flex-wrap gap-2 text-[12px]">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mr-1">Popular</span>
                    @foreach ($popularChips as $chip)
                        <a href="{{ route('vehicles.index') }}{{ $chip['query_string'] ?? '' }}" class="border border-line hover:border-toco-navy px-2.5 py-1 rounded-sm">{{ $chip['label'] ?? '' }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Featured grid with browse-by-make and browse-by-body-type sidebars --}}
    @include('partials.home-featured', ['featured' => $featured, 'makesWithCounts' => $makesWithCounts, 'bodyTypesWithCounts' => $bodyTypesWithCounts, 'totalPublished' => $totalPublished])

    {{-- Why Toco + How it works --}}
    @include('partials.home-why', ['content' => $content])

    {{-- CTA strip --}}
    @php
        $cta = $content['cta'] ?? [];
        $ctaKicker = $cta['kicker'] ?? 'Ready to import?';
        $ctaHeadline = $cta['headline'] ?? 'Tell us what you want — we\'ll quote and ship it.';
        $ctaBody = $cta['body'] ?? 'Get a CIF estimate to your nearest port, in your currency. No commitment until you\'re happy with the deal.';
        $ctaPrimaryLabel = $cta['button_primary_label'] ?? 'Request a quote';
        $ctaPrimaryUrl = $cta['button_primary_url'] ?? route('register');
        $ctaSecondaryLabel = $cta['button_secondary_label'] ?? 'Browse stock';
        $ctaSecondaryUrl = $cta['button_secondary_url'] ?? route('vehicles.index');
    @endphp
    <section id="contact" class="bg-toco-black text-white">
        <div class="max-w-[1440px] mx-auto px-6 py-12 md:py-16 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-6 items-center">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $ctaKicker }}</p>
                <h2 class="text-3xl md:text-4xl font-extrabold mt-2 leading-tight">{{ $ctaHeadline }}</h2>
                <p class="text-white/70 mt-3 max-w-xl">{{ $ctaBody }}</p>
            </div>
            <div class="flex gap-3 md:justify-end">
                <a href="{{ $ctaPrimaryUrl }}" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-5 py-3 rounded-sm">{{ $ctaPrimaryLabel }}</a>
                <a href="{{ $ctaSecondaryUrl }}" class="border border-white/30 hover:bg-white/10 text-white font-bold uppercase tracking-widest text-xs px-5 py-3 rounded-sm">{{ $ctaSecondaryLabel }}</a>
            </div>
        </div>
    </section>
</x-layouts.site>
