<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Toco Japan') }}</title>
    <meta name="description" content="{{ $description ?? 'Toco Japan — quality Japanese vehicles, exported worldwide. Browse stock, get a CIF quote, and import with confidence.' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-sans text-ink antialiased bg-surface min-h-screen flex flex-col">
    {{-- Fraud notice bar --}}
    <div class="bg-[#FDECEE] text-toco-navy text-[11px] font-semibold tracking-wider uppercase py-2 px-4 text-center font-mono">
        BE AWARE OF FRAUDSTERS · always verify our company details before sending any payment
    </div>

    {{-- Top bar --}}
    <div class="bg-toco-navy text-white/80 text-[12px] tracking-wide border-b border-white/5">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 2xl:px-8 py-2 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 sm:gap-5">
                @isset($topBarLeft)
                    {{ $topBarLeft }}
                @else
                    <span class="inline-flex items-center gap-1.5 notranslate" translate="no">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                        <select
                            id="lang_picker"
                            onchange="window.setSiteLanguage(this.value)"
                            class="bg-transparent border-0 text-white/80 hover:text-white text-[12px] cursor-pointer focus:outline-none pr-1"
                        >
                            <option value="en" class="text-toco-navy">English</option>
                            <option value="ja" class="text-toco-navy">日本語</option>
                            <option value="es" class="text-toco-navy">Español</option>
                            <option value="fr" class="text-toco-navy">Français</option>
                            <option value="de" class="text-toco-navy">Deutsch</option>
                            <option value="ru" class="text-toco-navy">Русский</option>
                            <option value="zh-CN" class="text-toco-navy">中文</option>
                            <option value="ko" class="text-toco-navy">한국어</option>
                            <option value="ar" class="text-toco-navy">العربية</option>
                            <option value="th" class="text-toco-navy">ไทย</option>
                            <option value="vi" class="text-toco-navy">Tiếng Việt</option>
                            <option value="sw" class="text-toco-navy">Kiswahili</option>
                        </select>
                        <span id="google_translate_element" class="hidden"></span>
                    </span>
                    @if (! empty($currencyOptions))
                        <form method="POST" action="#" id="currencyForm" class="inline">
                            @csrf
                            <select
                                name="code"
                                onchange="document.getElementById('currencyForm').action = '/currency/' + this.value; document.getElementById('currencyForm').submit();"
                                class="bg-transparent border-0 text-white/80 hover:text-white text-[12px] cursor-pointer focus:outline-none notranslate"
                            >
                                @foreach ($currencyOptions as $c)
                                    <option value="{{ $c['code'] }}" {{ $c['code'] === $currentCurrency ? 'selected' : '' }} class="text-toco-navy">{{ $c['code'] }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif

                    {{-- Destination port picker — drives CIF on listing + detail --}}
                    <form method="POST" action="{{ route('destination.set') }}" id="destForm" class="inline-flex items-center gap-1">
                        @csrf
                        <span class="hidden md:inline">📍</span>
                        <select
                            name="port_id"
                            onchange="document.getElementById('destForm').submit()"
                            class="bg-transparent border-0 text-white/80 hover:text-white text-[12px] cursor-pointer focus:outline-none notranslate max-w-[140px]"
                            title="Set destination port for CIF prices"
                        >
                            <option value="">Set destination</option>
                            @foreach (\App\Models\Country::query()->where('is_active', true)->with(['ports' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])->orderBy('sort_order')->orderBy('name')->get() as $__c)
                                <optgroup label="{{ $__c->name }}">
                                    @foreach ($__c->ports as $__p)
                                        <option value="{{ $__p->id }}" class="text-toco-navy" {{ $destPort && $destPort->id === $__p->id ? 'selected' : '' }}>{{ $__p->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </form>
                @endisset
            </div>
            <div class="flex items-center gap-3 sm:gap-5">
                @isset($topBarRight)
                    {{ $topBarRight }}
                @else
                    <a href="#" class="hidden sm:inline-flex items-center gap-1.5 hover:text-white">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-toco-red"></span>
                        <span class="hidden lg:inline">Live: 12 buyers viewing now</span>
                        <span class="lg:hidden">Live</span>
                    </a>
                    <a href="#" class="hidden md:inline-flex hover:text-white">Track shipment</a>
                    <a href="#" class="hidden md:inline-flex hover:text-white">+81 90-1234-5678</a>
                @endisset
            </div>
        </div>
    </div>

    {{-- Sticky header --}}
    <header class="sticky top-0 z-30 bg-white border-b border-line" x-data="{ mobileOpen: false }">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 2xl:px-8 h-16 flex items-center justify-between gap-3 sm:gap-6">
            @php($headerLogo = app(\App\Settings\GeneralSettings::class)->header_logo ?? null)
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 shrink-0">
                @if ($headerLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($headerLogo) }}" alt="{{ config('app.name', 'Toco Japan') }}" class="h-9 sm:h-10 w-auto">
                @else
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-sm bg-toco-red text-white font-bold text-sm font-mono">TJ</span>
                    <span class="font-extrabold tracking-tight text-toco-navy text-lg hidden sm:inline">Toco Japan</span>
                @endif
            </a>
            <nav class="hidden lg:flex items-center gap-7 text-[13px] font-semibold text-ink">
                <a href="{{ route('home') }}" class="hover:text-toco-red">Home</a>
                <a href="{{ route('vehicles.index') }}" class="hover:text-toco-red">Vehicles</a>
                <a href="#how-it-works" class="hover:text-toco-red">How it works</a>
                <a href="#why-toco" class="hover:text-toco-red">Why Toco</a>
                <a href="#contact" class="hover:text-toco-red">Contact</a>
            </nav>
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                @php($wishlistCount = count($favoritedIds ?? []))
                <a href="{{ Auth::check() ? route('favorites.index') : route('login') }}" class="relative inline-flex items-center text-ink hover:text-toco-red" title="My wishlist">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="{{ $wishlistCount ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.5-7 10-7 10Z"/></svg>
                    @if ($wishlistCount > 0)
                        <span class="absolute -top-1 -right-1.5 min-w-[16px] h-[16px] px-1 bg-toco-red text-white text-[10px] font-bold rounded-full grid place-items-center">{{ $wishlistCount }}</span>
                    @endif
                </a>
                @auth
                    <a href="{{ route('orders.index') }}" class="relative inline-flex items-center text-ink hover:text-toco-red" title="My orders & messages">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        @if (($unreadMessageCount ?? 0) > 0)
                            <span class="absolute -top-1 -right-1.5 min-w-[16px] h-[16px] px-1 bg-toco-red text-white text-[10px] font-bold rounded-full grid place-items-center">{{ $unreadMessageCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('dashboard') }}" class="hidden sm:inline text-[12px] font-semibold text-ink hover:text-toco-red">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-block text-[12px] font-semibold text-ink hover:text-toco-red">Sign in</a>
                    <a href="{{ route('register') }}" class="bg-toco-red hover:bg-toco-red-deep text-white text-[11px] font-bold uppercase tracking-widest px-3 sm:px-3.5 py-2 rounded-sm">Register</a>
                @endauth
                <button type="button" @click="mobileOpen = !mobileOpen" aria-label="Menu" class="lg:hidden inline-flex items-center justify-center w-9 h-9 -mr-1 rounded-sm hover:bg-toco-silver-2 text-toco-navy">
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
            class="lg:hidden fixed inset-0 top-[88px] bg-black/40 z-40"
        >
            <nav
                x-show="mobileOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-y-2 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                class="bg-white border-b border-line shadow-lg max-w-[1600px] mx-auto"
            >
                <ul class="divide-y divide-line text-base font-semibold text-ink">
                    <li><a href="{{ route('home') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Home</a></li>
                    <li><a href="{{ route('vehicles.index') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">Vehicles</a></li>
                    <li><a href="{{ route('cif.index') }}" class="block px-5 py-3.5 hover:bg-toco-silver-2">CIF calculator</a></li>
                    <li><a href="{{ route('home') }}#how-it-works" class="block px-5 py-3.5 hover:bg-toco-silver-2">How it works</a></li>
                    <li><a href="{{ route('home') }}#why-toco" class="block px-5 py-3.5 hover:bg-toco-silver-2">Why Toco</a></li>
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

    <footer class="bg-toco-navy-deep text-white/85 mt-12">
        <div class="max-w-[1440px] mx-auto px-6 py-12 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-8 text-sm">
            <div class="col-span-2 lg:col-span-1">
                <div class="inline-flex items-center gap-2.5 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-sm bg-toco-red text-white font-bold text-sm font-mono">TJ</span>
                    <span class="font-extrabold tracking-tight text-white text-lg">Toco Japan</span>
                </div>
                <p class="text-white/70 text-[13px] leading-relaxed max-w-xs">
                    Quality Japanese vehicles, exported worldwide since 2009. We source, inspect and ship — you import with confidence.
                </p>
            </div>
            <div>
                <h4 class="font-bold text-white uppercase tracking-widest text-xs mb-3">Buy</h4>
                <ul class="space-y-2 text-white/75">
                    <li><a href="{{ route('vehicles.index') }}" class="hover:text-white">Browse stock</a></li>
                    <li><a href="{{ route('vehicles.index') }}?body_type=suv" class="hover:text-white">SUVs</a></li>
                    <li><a href="{{ route('vehicles.index') }}?body_type=mini-truck" class="hover:text-white">Kei trucks</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white">Request a quote</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-white uppercase tracking-widest text-xs mb-3">Ship</h4>
                <ul class="space-y-2 text-white/75">
                    <li><a href="#" class="hover:text-white">Destinations</a></li>
                    <li><a href="#" class="hover:text-white">CIF calculator</a></li>
                    <li><a href="#" class="hover:text-white">Shipping schedule</a></li>
                    <li><a href="#" class="hover:text-white">Inspection</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-white uppercase tracking-widest text-xs mb-3">Trust</h4>
                <ul class="space-y-2 text-white/75">
                    <li><a href="#" class="hover:text-white">About Toco</a></li>
                    <li><a href="#" class="hover:text-white">Process</a></li>
                    <li><a href="#" class="hover:text-white">Reviews</a></li>
                    <li><a href="#" class="hover:text-white">Banking</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-white uppercase tracking-widest text-xs mb-3">Contact</h4>
                <ul class="space-y-2 text-white/75">
                    <li>sales@tocojapan.com</li>
                    <li>+81 (0) 00 0000 0000</li>
                    <li>Yokohama, Japan</li>
                </ul>
            </div>
        </div>
        @php($footerLogos = app(\App\Settings\GeneralSettings::class)->footer_logos ?? [])
        @if (! empty($footerLogos))
            <div class="border-t border-white/10 bg-white/95">
                <div class="max-w-[1440px] mx-auto px-6 py-5 flex flex-wrap items-center justify-center gap-x-10 gap-y-4">
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
            <div class="max-w-[1440px] mx-auto px-6 py-4 text-[11px] text-white/55 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <span>&copy; {{ date('Y') }} Toco Japan Co., Ltd. All rights reserved.</span>
                <span class="font-mono uppercase tracking-widest">JUMVEA · JEVIC · JAAI member</span>
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
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,ja,es,fr,zh-CN,ru,ar,de,ko,th,vi,sw',
                autoDisplay: false,
            }, 'google_translate_element');
            // Once initialized, sync the picker to whatever cookie says we're showing.
            var current = (document.cookie.match(/(?:^|;\s*)googtrans=\/en\/([\w-]+)/) || [])[1] || 'en';
            var sel = document.getElementById('lang_picker');
            if (sel) sel.value = current;
        }

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
    </script>
    <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async></script>
    @stack('scripts')
</body>
</html>
