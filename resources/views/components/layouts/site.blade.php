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
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-2 flex items-center justify-between gap-6">
            <div class="hidden sm:flex items-center gap-5">
                @isset($topBarLeft)
                    {{ $topBarLeft }}
                @else
                    <span class="inline-flex items-center gap-1.5">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                        <span id="google_translate_element" class="notranslate"></span>
                    </span>
                    <span>USD ▾</span>
                @endisset
            </div>
            <div class="flex items-center gap-5">
                @isset($topBarRight)
                    {{ $topBarRight }}
                @else
                    <a href="#" class="inline-flex items-center gap-1.5 hover:text-white">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-toco-red"></span>
                        Live: 12 buyers viewing now
                    </a>
                    <a href="#" class="hidden md:inline-flex hover:text-white">Track shipment</a>
                    <a href="#" class="hidden md:inline-flex hover:text-white">+81 90-1234-5678</a>
                @endisset
            </div>
        </div>
    </div>

    {{-- Sticky header --}}
    <header class="sticky top-0 z-30 bg-white border-b border-line">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 h-16 flex items-center justify-between gap-6">
            @php($headerLogo = app(\App\Settings\GeneralSettings::class)->header_logo ?? null)
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 shrink-0">
                @if ($headerLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($headerLogo) }}" alt="{{ config('app.name', 'Toco Japan') }}" class="h-10 w-auto">
                @else
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-sm bg-toco-red text-white font-bold text-sm font-mono">TJ</span>
                    <span class="font-extrabold tracking-tight text-toco-navy text-lg">Toco Japan</span>
                @endif
            </a>
            <nav class="hidden lg:flex items-center gap-7 text-[13px] font-semibold text-ink">
                <a href="{{ route('home') }}" class="hover:text-toco-red">Home</a>
                <a href="{{ route('vehicles.index') }}" class="hover:text-toco-red">Vehicles</a>
                <a href="#how-it-works" class="hover:text-toco-red">How it works</a>
                <a href="#why-toco" class="hover:text-toco-red">Why Toco</a>
                <a href="#contact" class="hover:text-toco-red">Contact</a>
            </nav>
            <div class="flex items-center gap-2 shrink-0">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-[12px] font-semibold text-ink hover:text-toco-red">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-block text-[12px] font-semibold text-ink hover:text-toco-red">Sign in</a>
                    <a href="{{ route('register') }}" class="bg-toco-red hover:bg-toco-red-deep text-white text-[11px] font-bold uppercase tracking-widest px-3.5 py-2 rounded-sm">Register</a>
                @endauth
            </div>
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

    {{-- Google Translate widget for the header language picker --}}
    <style>
        #google_translate_element select { background: transparent; color: currentColor; border: 0; padding: 0 4px; font: inherit; cursor: pointer; }
        #google_translate_element select option { color: #1a1a1a; }
        .goog-te-banner-frame.skiptranslate, .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
    </style>
    <script>
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,ja,es,fr,zh-CN,ru,ar,de,ko,th,vi,sw',
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                autoDisplay: false,
            }, 'google_translate_element');
        }
    </script>
    <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async></script>
</body>
</html>
