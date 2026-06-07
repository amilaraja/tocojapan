<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $gaId = app(\App\Settings\GeneralSettings::class)->google_analytics_id ?? null;
    @endphp
    @if ($gaId)
        {{-- Google Analytics (GA4) — measurement ID set in Site settings --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}');
        </script>
    @endif
    @php
        $resolvedTitle = $title ?? config('app.name', 'Toco Japan');
        $resolvedDescription = $description ?? 'Toco Japan — quality Japanese vehicles, exported worldwide. Browse stock, get a CIF quote, and import with confidence.';
        $resolvedOgImage = $ogImage ?? asset('img/footer-logos/toco.png');
        $resolvedCanonical = $canonical ?? url()->current();
    @endphp
    <title>{{ $resolvedTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <link rel="canonical" href="{{ $resolvedCanonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'Toco Japan') }}">
    <meta property="og:title" content="{{ $resolvedTitle }}">
    <meta property="og:description" content="{{ $resolvedDescription }}">
    <meta property="og:url" content="{{ $resolvedCanonical }}">
    <meta property="og:image" content="{{ $resolvedOgImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $resolvedTitle }}">
    <meta name="twitter:description" content="{{ $resolvedDescription }}">
    <meta name="twitter:image" content="{{ $resolvedOgImage }}">
    {{-- Hide Alpine elements until Alpine initialises — prevents modals/menus
         flashing on every page load. Must be inline so it applies pre-render. --}}
    <style>[x-cloak]{display:none !important;}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-sans text-ink antialiased bg-surface min-h-screen flex flex-col">
    {{-- Brand + fraud notice bar --}}
    <div class="bg-[#FDECEE] text-toco-navy text-[11px] font-semibold tracking-wider uppercase py-2 font-mono">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 2xl:px-8 flex items-center justify-between gap-4">
            @if (request()->routeIs('home'))
                <h1 class="shrink-0 text-[11px] font-semibold tracking-wider uppercase m-0">Toco - Japanese Used Cars For Sale</h1>
            @else
                <span class="shrink-0">Toco - Japanese Used Cars For Sale</span>
            @endif
            <span class="text-right hidden sm:inline">BE AWARE OF FRAUDSTERS · always verify our company details before sending any payment</span>
        </div>
    </div>

    {{-- Top bar — relative z-40 so its absolute-positioned dropdowns
         (language + currency) paint above the sticky header (z-30). --}}
    <div class="relative z-40 bg-toco-navy text-white/80 text-[12px] tracking-wide border-b border-white/5">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 2xl:px-8 py-0.5 sm:py-2 flex items-center justify-between gap-4 [&_select]:h-7 [&_select]:py-0 sm:[&_select]:h-auto sm:[&_select]:py-1.5">
            <div class="flex items-center gap-3 sm:gap-5">
                @isset($topBarLeft)
                    {{ $topBarLeft }}
                @else
                    @php
                        // Language picker — flag SVGs live in public/img/flags/.
                        $languages = [
                            ['code' => 'en', 'label' => 'English',  'flag' => '/img/flags/gb.svg'],
                            ['code' => 'ja', 'label' => '日本語',     'flag' => '/img/flags/jp.svg'],
                            ['code' => 'fr', 'label' => 'Français', 'flag' => '/img/flags/fr.svg'],
                            ['code' => 'pt', 'label' => 'Português','flag' => '/img/flags/pt.svg'],
                            ['code' => 'es', 'label' => 'Español',  'flag' => '/img/flags/es.svg'],
                            ['code' => 'sw', 'label' => 'Kiswahili','flag' => '/img/flags/tz.svg'],
                        ];
                        // Map currency codes → flag file. Falls back to a generic globe if unmapped.
                        $currencyFlags = [
                            'USD' => '/img/flags/us.svg',
                            'JPY' => '/img/flags/jp.svg',
                            'EUR' => '/img/flags/eu.svg',
                            'GBP' => '/img/flags/gb.svg',
                        ];
                    @endphp

                    {{-- Language picker (custom — native <select> can't render flag images) --}}
                    <div class="relative notranslate" translate="no"
                         x-data="{ open: false, current: (window.tocoCurrentLang && window.tocoCurrentLang()) || 'en' }">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                                class="inline-flex items-center gap-1.5 text-white/80 hover:text-white text-[12px] focus:outline-none">
                            @foreach ($languages as $lng)
                                <img src="{{ $lng['flag'] }}" alt="" width="18" height="12" loading="lazy"
                                     x-show="current === '{{ $lng['code'] }}'" x-cloak
                                     class="block rounded-[1px] ring-1 ring-white/30 shadow-sm">
                            @endforeach
                            <span class="ml-1" x-text="({ en: 'English', ja: '日本語', fr: 'Français', pt: 'Português', es: 'Español', sw: 'Kiswahili' })[current] || 'Language'"></span>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" :class="open ? 'rotate-180' : ''" class="transition-transform"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <ul x-show="open" x-cloak x-transition.opacity
                            class="absolute left-0 top-full mt-1 z-50 min-w-[160px] bg-white border border-line rounded-sm shadow-lg overflow-hidden text-toco-navy text-[12px]">
                            @foreach ($languages as $lng)
                                <li>
                                    <button type="button" @click="current = '{{ $lng['code'] }}'; open = false; window.setSiteLanguage('{{ $lng['code'] }}')"
                                            class="w-full px-3 py-2 flex items-center gap-2 hover:bg-toco-silver-2 text-left">
                                        <img src="{{ $lng['flag'] }}" alt="" width="20" height="14" loading="lazy" class="block shrink-0 rounded-[1px] ring-1 ring-line">
                                        <span class="font-semibold">{{ $lng['label'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                        <span id="google_translate_element" class="hidden"></span>
                    </div>

                    {{-- Currency picker (custom — flag image + currency code) --}}
                    @if (! empty($currencyOptions))
                        <div class="relative notranslate" translate="no" x-data="{ open: false }">
                            <button type="button" @click="open = !open" @click.outside="open = false"
                                    class="inline-flex items-center gap-1.5 text-white/80 hover:text-white text-[12px] focus:outline-none">
                                @if (! empty($currencyFlags[$currentCurrency]))
                                    <img src="{{ $currencyFlags[$currentCurrency] }}" alt="" width="18" height="12" loading="lazy" class="block rounded-[1px] ring-1 ring-white/30 shadow-sm">
                                @endif
                                <span class="ml-1 font-semibold tabular-nums">{{ $currentCurrency }}</span>
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" :class="open ? 'rotate-180' : ''" class="transition-transform"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <ul x-show="open" x-cloak x-transition.opacity
                                class="absolute left-0 top-full mt-1 z-50 min-w-[140px] bg-white border border-line rounded-sm shadow-lg overflow-hidden text-toco-navy text-[12px]">
                                @foreach ($currencyOptions as $c)
                                    <li>
                                        <form method="POST" action="/currency/{{ $c['code'] }}">@csrf
                                            <button type="submit" class="w-full px-3 py-2 flex items-center gap-2 hover:bg-toco-silver-2 text-left {{ $c['code'] === $currentCurrency ? 'bg-toco-silver-2' : '' }}">
                                                @if (! empty($currencyFlags[$c['code']]))
                                                    <img src="{{ $currencyFlags[$c['code']] }}" alt="" width="20" height="14" loading="lazy" class="block shrink-0 rounded-[1px] ring-1 ring-line">
                                                @else
                                                    <span class="w-[20px] h-[14px] grid place-items-center bg-toco-silver-2 text-[9px] font-bold">{{ substr($c['code'], 0, 1) }}</span>
                                                @endif
                                                <span class="font-semibold tabular-nums">{{ $c['code'] }}</span>
                                                @if (! empty($c['symbol']) && $c['symbol'] !== $c['code'])
                                                    <span class="text-ink-soft text-[11px] ml-auto">{{ $c['symbol'] }}</span>
                                                @endif
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Destination picker — opens a country + port dialog --}}
                    @php
                        $destCountries = \App\Models\Country::query()
                            ->where('is_active', true)
                            ->whereHas('ports', fn ($q) => $q->where('is_active', true))
                            ->with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                            ->orderBy('name')
                            ->get();
                    @endphp
                    <div
                        class="inline-flex notranslate" translate="no"
                        x-data="{
                            open: false,
                            countryId: '',
                            portId: '',
                            ports: [],
                            countries: @js($destCountries->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'ports' => $c->ports->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all()])),
                            init() {
                                const dc = '{{ $destPort?->country_id }}', dp = '{{ $destPort?->id }}';
                                if (! dc) {
                                    // No destination yet — prompt the visitor to set one,
                                    // once per browser session so it isn't naggy.
                                    if (! sessionStorage.getItem('toco_dest_prompted')) {
                                        sessionStorage.setItem('toco_dest_prompted', '1');
                                        this.$nextTick(() => { this.open = true; });
                                    }
                                    return;
                                }
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
                        }"
                    >
                        <button type="button" @click="open = true"
                            class="inline-flex items-center gap-1.5 text-white/80 hover:text-white text-[12px] cursor-pointer focus:outline-none"
                            title="Set your destination for CIF prices">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="max-w-[180px] truncate">
                                @if ($destPort)
                                    {{ $destPort->country->name ?? '' }} · {{ $destPort->name }}
                                @else
                                    Set destination
                                @endif
                            </span>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="shrink-0 opacity-70"><path d="m6 9 6 6 6-6"/></svg>
                        </button>

                        {{-- Dialog --}}
                        <div x-show="open" x-cloak @keydown.escape.window="open = false"
                            class="fixed inset-0 z-[60] flex items-start justify-center p-4 pt-24 bg-black/50"
                            @click.self="open = false">
                            <div class="bg-white text-ink rounded-sm shadow-2xl w-full max-w-md" @click.stop>
                                <div class="flex items-center justify-between px-5 py-3.5 border-b border-line">
                                    <h3 class="font-bold text-toco-navy text-sm">Choose your destination</h3>
                                    <button type="button" @click="open = false" aria-label="Close" class="text-ink-soft hover:text-toco-red">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('destination.set') }}" class="p-5 space-y-3">
                                    @csrf
                                    <p class="text-[12px] text-ink-soft">Pick your country and nearest port — CIF prices across the site update to that port.</p>
                                    <div>
                                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Country</label>
                                        <select x-model="countryId" @change="onCountry()" class="w-full border-line rounded-sm text-sm">
                                            <option value="">— Select country —</option>
                                            <template x-for="c in countries" :key="c.id">
                                                <option :value="c.id" x-text="c.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Port</label>
                                        <select name="port_id" x-model="portId" :disabled="!ports.length" class="w-full border-line rounded-sm text-sm disabled:bg-toco-silver-2">
                                            <option value="">— Select port —</option>
                                            <template x-for="p in ports" :key="p.id">
                                                <option :value="p.id" x-text="p.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <button type="submit" :disabled="!portId"
                                        class="w-full bg-toco-red hover:bg-toco-red-deep disabled:opacity-50 text-white font-bold uppercase tracking-widest text-[11px] px-4 py-2.5 rounded-sm">
                                        Apply destination
                                    </button>
                                </form>
                                @if ($destPort)
                                    <form method="POST" action="{{ route('destination.set') }}" class="px-5 pb-4 -mt-1 text-center">
                                        @csrf
                                        <button type="submit" class="text-[12px] font-semibold text-ink-soft hover:text-toco-red">Clear destination</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endisset
            </div>
            <div class="flex items-center gap-3 sm:gap-5">
                @isset($topBarRight)
                    {{ $topBarRight }}
                @else
                    <span class="hidden md:inline-flex items-center gap-1.5 notranslate" translate="no">
                        <span class="text-white/70">Japan time</span>
                        <span id="jp-clock" class="font-mono text-white tabular-nums">—</span>
                    </span>
                    <a href="#" class="hidden lg:inline-flex items-center gap-1.5 hover:text-white">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-toco-red"></span>
                        <span>Live: {{ rand(10, 20) }} buyers viewing now</span>
                    </a>
                    <a href="https://wa.me/819057628702" target="_blank" rel="noopener" class="hidden sm:inline-flex items-center gap-1.5 hover:text-white notranslate" translate="no">
                        <x-icons.whatsapp class="w-3.5 h-3.5 shrink-0" />
                        <span>+81 90 5762 8702</span>
                    </a>
                @endisset
            </div>
        </div>
    </div>

    {{-- Sticky header --}}
    <header class="sticky top-0 z-30 bg-white border-b border-line" x-data="{ mobileOpen: false }">
        {{-- Header — 3 columns: logo · (menu + search) · actions --}}
        @php($nav = [
            ['Home', route('home'), request()->is('/')],
            ['Stock List', route('vehicles.index'), request()->is('vehicles*')],
            ['Spareparts', route('cms.page', 'order-spareparts'), request()->is('order-spareparts')],
            ['How to Buy', route('cms.page', 'how-to-buy-cars-and-other-vehicles'), request()->is('how-to-buy-cars-and-other-vehicles')],
            ['FAQs', route('cms.page', 'faqs'), request()->is('faqs')],
            ['Contact Us', route('cms.page', 'contact'), request()->is('contact')],
        ])
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 2xl:px-8 py-3 flex items-center gap-4 sm:gap-8">
            {{-- Column 1 — logo --}}
            @php($headerLogo = app(\App\Settings\GeneralSettings::class)->header_logo ?? null)
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 shrink-0">
                @if ($headerLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($headerLogo) }}" alt="{{ config('app.name', 'Toco Japan') }}" class="h-10 sm:h-16 w-auto">
                @else
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-sm bg-toco-red text-white font-bold text-base font-mono">TJ</span>
                    <span class="font-extrabold tracking-tight text-toco-navy text-xl hidden sm:inline">Toco Japan</span>
                @endif
            </a>

            {{-- Column 2 — menu + search (centered) --}}
            <div class="hidden lg:flex flex-1 flex-col items-center gap-2.5 min-w-0">
                <nav>
                    <ul class="flex items-center justify-center gap-8 text-[13px] font-bold uppercase tracking-wide">
                        @foreach ($nav as [$label, $url, $active])
                            <li>
                                <a href="{{ $url }}" class="transition hover:text-toco-red {{ $active ? 'text-toco-red' : 'text-toco-navy' }}">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
                <form action="{{ route('vehicles.index') }}" method="GET" class="flex items-stretch h-10 w-full max-w-[600px] rounded-sm border border-line overflow-hidden bg-white">
                    <select name="search_by" aria-label="Search type" class="bg-toco-silver-2 border-0 border-r border-line text-[13px] font-semibold text-toco-navy focus:ring-0 pl-3 pr-8 cursor-pointer notranslate">
                        <option value="keyword">By Keyword</option>
                        <option value="ref">By Stock ID / Ref</option>
                    </select>
                    <input type="text" name="q" value="{{ request('q') }}" aria-label="Search vehicles" placeholder="Search by make, model, year…" class="flex-1 min-w-0 border-0 text-[13px] text-ink placeholder:text-ink-soft focus:ring-0 px-3">
                    <button type="submit" aria-label="Search" class="bg-toco-red hover:bg-toco-red-deep text-white px-4 grid place-items-center transition">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    </button>
                </form>
            </div>

            {{-- Column 3 — actions --}}
            <div class="flex items-center gap-2 sm:gap-4 shrink-0 ml-auto">
                @php($wishlistCount = count($favoritedIds ?? []))
                <a href="{{ Auth::check() ? route('favorites.index') : route('login') }}" class="relative inline-flex items-center text-ink hover:text-toco-red" title="My wishlist">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="{{ $wishlistCount ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.5-7 10-7 10Z"/></svg>
                    @if ($wishlistCount > 0)
                        <span class="absolute -top-1 -right-1.5 min-w-[16px] h-[16px] px-1 bg-toco-red text-white text-[10px] font-bold rounded-full grid place-items-center">{{ $wishlistCount }}</span>
                    @endif
                </a>
                @auth
                    <a href="{{ route('profile.edit') }}" class="relative inline-flex items-center text-ink hover:text-toco-red" title="My account">
                        @if (Auth::user()->avatarUrl())
                            <img src="{{ Auth::user()->avatarUrl() }}" alt="" class="w-7 h-7 rounded-full object-cover border border-line">
                        @else
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        @endif
                    </a>
                    <a href="{{ route('dashboard') }}" class="relative hidden sm:inline-flex items-center text-[13px] font-semibold text-ink hover:text-toco-red">
                        Dashboard
                        @if (($unreadMessageCount ?? 0) > 0)
                            <span class="ml-1 min-w-[16px] h-[16px] px-1 bg-toco-red text-white text-[10px] font-bold rounded-full grid place-items-center">{{ $unreadMessageCount }}</span>
                        @endif
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center text-[13px] font-semibold text-ink hover:text-toco-red">Sign in</a>
                    <a href="{{ route('register') }}" class="bg-toco-red hover:bg-toco-red-deep text-white text-[11px] sm:text-[12px] font-bold uppercase tracking-widest px-3 sm:px-4 py-2 sm:py-2.5 rounded-sm">Register</a>
                @endauth
                <button type="button" @click="mobileOpen = !mobileOpen" aria-label="Menu" class="lg:hidden inline-flex items-center justify-center w-9 h-9 shrink-0 rounded-sm hover:bg-toco-silver-2 text-toco-navy">
                    <svg x-show="!mobileOpen" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileOpen" x-cloak width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M6 18 18 6"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile drawer --}}
        <div
            x-show="mobileOpen"
            x-cloak
            x-transition.opacity
            @click.self="mobileOpen = false"
            class="lg:hidden absolute inset-x-0 top-full bg-black/40 h-screen z-40"
        >
            <nav
                x-show="mobileOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-y-2 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                class="bg-white border-b border-line shadow-lg"
            >
                <form action="{{ route('vehicles.index') }}" method="GET" class="flex items-stretch h-11 m-4 rounded-sm border border-line overflow-hidden">
                    <input type="text" name="q" value="{{ request('q') }}" aria-label="Search vehicles" placeholder="Search by make, model, year…" class="flex-1 min-w-0 border-0 text-[13px] focus:ring-0 px-3">
                    <button type="submit" aria-label="Search" class="bg-toco-red text-white px-4 grid place-items-center">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    </button>
                </form>
                <ul class="divide-y divide-line text-base font-semibold text-ink border-t border-line">
                    <li><a href="{{ route('home') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Home</a></li>
                    <li><a href="{{ route('vehicles.index') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Stock List</a></li>
                    <li><a href="{{ route('cms.page', 'order-spareparts') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Spareparts</a></li>
                    <li><a href="{{ route('cms.page', 'how-to-buy-cars-and-other-vehicles') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">How to Buy</a></li>
                    <li><a href="{{ route('cms.page', 'faqs') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">FAQs</a></li>
                    <li><a href="{{ route('cms.page', 'contact') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Contact Us</a></li>
                    @auth
                        <li><a href="{{ route('dashboard') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Dashboard</a></li>
                        <li><a href="{{ route('orders.index') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">My orders @if (($unreadMessageCount ?? 0) > 0)<span class="ml-2 inline-block bg-toco-red text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $unreadMessageCount }}</span>@endif</a></li>
                        <li><a href="{{ route('favorites.index') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">My wishlist @if ($wishlistCount > 0)<span class="ml-2 inline-block bg-toco-red text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $wishlistCount }}</span>@endif</a></li>
                    @else
                        <li><a href="{{ route('login') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Sign in</a></li>
                    @endauth
                </ul>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="bg-toco-navy-deep text-white/85 {{ request()->routeIs('home') ? '' : 'mt-12' }}">
        <div class="max-w-[1440px] mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[1.7fr_1fr_1fr_1fr] gap-8 lg:gap-10 text-sm">
            {{-- Company + contact + social --}}
            <div>
                @php($footerLogo = app(\App\Settings\GeneralSettings::class)->header_logo ?? null)
                <a href="{{ route('home') }}" class="inline-block bg-white rounded-sm px-4 py-2.5 mb-5">
                    @if ($footerLogo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($footerLogo) }}" alt="{{ config('app.name', 'Toco Japan') }}" class="h-9 w-auto">
                    @else
                        <span class="font-extrabold tracking-tight text-toco-navy text-xl">TOCO</span>
                    @endif
                </a>
                <ul class="space-y-2.5 text-white/75 text-[13px]">
                    <li class="flex gap-2.5">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5 text-toco-red"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
                        <span>3400-1 Horigome-Cho, Sano City,<br>Tochigi, Japan. 327-0843</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-toco-red"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.69 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.33 1.85.56 2.81.69A2 2 0 0 1 22 16.92z"/></svg>
                        <a href="tel:+81283857224" class="hover:text-white">+81 283 85 7224</a>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-toco-red"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                        <span>+81 283 24 4569</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <x-icons.whatsapp class="w-[15px] h-[15px] shrink-0" />
                        <a href="https://wa.me/819057628702" target="_blank" rel="noopener" class="hover:text-white">+81 90 5762 8702</a>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-toco-red"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
                        <a href="mailto:info@tocojapan.com" class="hover:text-white">info@tocojapan.com</a>
                    </li>
                </ul>
                @php($socialLinks = app(\App\Settings\SocialSettings::class)->links ?? [])
                @php($socialRegistry = \App\Support\SocialPlatforms::all())
                @php($socialLinks = array_values(array_filter($socialLinks, fn ($l) => ! empty($l['url']) && isset($socialRegistry[$l['platform'] ?? '']))))
                @if (! empty($socialLinks))
                    <div class="flex items-center gap-3 mt-6">
                        <span class="font-bold uppercase tracking-widest text-xs text-white shrink-0">Follow us</span>
                        <div class="flex items-center gap-2">
                            @foreach ($socialLinks as $link)
                                @php($meta = $socialRegistry[$link['platform']])
                                @php($label = ! empty($link['label']) ? $link['label'] : $meta['name'])
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener" aria-label="{{ $label }}"
                                   class="w-8 h-8 rounded-full grid place-items-center hover:opacity-80 transition" style="background-color: {{ $meta['color'] }}">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="#fff"><path d="{{ $meta['svg'] }}"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- About Us --}}
            <div>
                <h4 class="font-bold text-white text-[15px] mb-3">About Us</h4>
                <ul class="space-y-2 text-white/75">
                    <li><a href="{{ route('cms.page', 'about-us') }}" class="hover:text-white">Company Profile</a></li>
                    <li><a href="{{ route('cms.page', 'bank-details') }}" class="hover:text-white">Bank Details</a></li>
                    <li><a href="{{ route('cms.page', 'customer-reviews') }}" class="hover:text-white">Customer Reviews</a></li>
                    <li><a href="{{ route('cms.page', 'contact') }}" class="hover:text-white">Contact Us</a></li>
                </ul>
            </div>

            {{-- Other --}}
            <div>
                <h4 class="font-bold text-white text-[15px] mb-3">Other</h4>
                <ul class="space-y-2 text-white/75">
                    <li><a href="{{ route('cms.page', 'how-to-buy-cars-and-other-vehicles') }}" class="hover:text-white">How to Buy</a></li>
                    <li><a href="{{ route('cms.page', 'import-regulations') }}" class="hover:text-white">Import Regulation</a></li>
                    <li><a href="{{ route('cms.page', 'shipping-schedule') }}" class="hover:text-white">Shipping Schedule</a></li>
                    <li><a href="{{ route('news.index') }}" class="hover:text-white">News and Updates</a></li>
                </ul>
            </div>

            {{-- Help and Support --}}
            <div>
                <h4 class="font-bold text-white text-[15px] mb-3">Help and Support</h4>
                <ul class="space-y-2 text-white/75">
                    <li><a href="{{ route('cms.page', 'faqs') }}" class="hover:text-white">FAQs</a></li>
                    <li><a href="{{ route('cms.page', 'contact') }}" class="hover:text-white">Inquiry</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white">Register</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-white">Login</a></li>
                </ul>
            </div>
        </div>
        @php($footerLogos = app(\App\Settings\GeneralSettings::class)->footer_logos ?? [])
        @if (! empty($footerLogos))
            <div class="border-t border-white/10 bg-white/95">
                <div class="max-w-[1440px] mx-auto px-6 py-5 flex flex-wrap items-center justify-center lg:justify-end gap-x-10 gap-y-4">
                    @foreach ($footerLogos as $logo)
                        @php($src = \Illuminate\Support\Facades\Storage::disk('public')->url($logo['image']))
                        @if (! empty($logo['link']))
                            <a href="{{ $logo['link'] }}" target="_blank" rel="noopener">
                                <img src="{{ $src }}" alt="{{ $logo['alt'] ?? '' }}" class="h-12 w-auto" loading="lazy">
                            </a>
                        @else
                            <img src="{{ $src }}" alt="{{ $logo['alt'] ?? '' }}" class="h-12 w-auto" loading="lazy">
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
        <div class="border-t border-white/10">
            <div class="max-w-[1440px] mx-auto px-6 py-4 text-[11px] text-white/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <span>
                    Copyright &copy; {{ date('Y') }} 有限会社 TOCO INTERNATIONAL | All rights Reserved.
                </span>
                {{-- Web design by Mobiz — https://mobiz.lk --}}
                <span class="flex items-center gap-4">
                    <a href="{{ route('cms.page', 'terms-and-conditions') }}" class="hover:text-white">Terms &amp; Conditions</a>
                    <a href="{{ route('cms.page', 'privacy-policy') }}" class="hover:text-white">Privacy Policy</a>
                    <a href="{{ route('sitemap') }}" class="hover:text-white">Site Map</a>
                </span>
            </div>
        </div>
    </footer>

    {{-- Google Translate — driven by a custom select; the default widget is hidden. --}}
    <style>
        #google_translate_element, .goog-te-banner-frame.skiptranslate, .goog-te-gadget-icon, .goog-logo-link, .goog-te-balloon-frame, #goog-gt-tt, .goog-tooltip { display: none !important; }
        body { top: 0 !important; position: static !important; }
        .goog-te-spinner-pos { display: none !important; }
        /* Kill Google's added top padding on body and any injected #goog-gt-tt tooltip on hover */
        font[style*="background-color: rgb(255, 255, 102)"] { background-color: transparent !important; box-shadow: none !important; }
    </style>
    <script>
        window.googleTranslateElementInit = function () {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,ja,fr,pt,es,sw',
                autoDisplay: false,
            }, 'google_translate_element');
            // Once initialized, sync the picker to whatever cookie says we're showing.
            var current = (document.cookie.match(/(?:^|;\s*)googtrans=\/en\/([\w-]+)/) || [])[1] || 'en';
            var sel = document.getElementById('lang_picker');
            if (sel) sel.value = current;
        };

        window.tocoCurrentLang = function () {
            try {
                var m = document.cookie.match(/(?:^|; )googtrans=\/[^\/]+\/([a-z]+)/);
                if (m) return m[1];
                m = document.cookie.match(/(?:^|; )toco_lang=([a-z]+)/);
                if (m) return m[1];
            } catch (e) {}
            return 'en';
        };

        window.setSiteLanguage = function (lang) {
            // Google Translate reads the `googtrans` cookie; set it for the
            // host + parent domain so the choice survives subdomain hops.
            var host = location.hostname;
            var parent = host.split('.').slice(-2).join('.');
            var value = lang === 'en' ? '/en/en' : '/en/' + lang;
            document.cookie = 'googtrans=' + value + ';path=/;max-age=31536000';
            document.cookie = 'googtrans=' + value + ';path=/;domain=.' + parent + ';max-age=31536000';
            location.reload();
        };

        // Google Translate is heavy — only load it when the visitor is
        // actually viewing the site in a non-English language. English
        // visitors (the majority) never pay for it.
        (function () {
            var m = document.cookie.match(/(?:^|;\s*)googtrans=\/[\w-]+\/([\w-]+)/);
            if (!m || m[1] === 'en') return;
            ['//translate.googleapis.com', '//translate-pa.googleapis.com', '//www.gstatic.com'].forEach(function (origin) {
                var l = document.createElement('link');
                l.rel = 'preconnect'; l.href = origin; l.crossOrigin = '';
                document.head.appendChild(l);
            });
            var s = document.createElement('script');
            s.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            s.async = true;
            document.head.appendChild(s);
        })();
    </script>

    {{-- Japan time clock (Asia/Tokyo) --}}
    <script>
        (function () {
            var el = document.getElementById('jp-clock');
            if (!el) return;
            function tick() {
                el.textContent = new Date().toLocaleTimeString('en-US', {
                    timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit', hour12: true
                }).toLowerCase();
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
    @stack('scripts')
</body>
</html>
