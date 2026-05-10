<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Toco Japan — new platform (build {{ config('app.env') }})</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col">
    {{-- Notice bar (matches v5 design) --}}
    <div class="bg-toco-red text-white text-xs font-semibold tracking-wide py-2 px-4 text-center">
        BE AWARE OF FRAUDSTERS — always verify our company details before sending payment.
    </div>

    {{-- Header --}}
    <header class="border-b border-line bg-surface">
        <div class="mx-auto max-w-[1440px] px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-9 w-9 items-center justify-center bg-toco-navy text-white font-mono text-xs tracking-widest">
                    TJ
                </span>
                <div>
                    <div class="text-toco-navy font-extrabold text-lg leading-none">Toco Japan</div>
                    <div class="font-mono text-[10px] tracking-[0.2em] text-ink-soft uppercase mt-0.5">
                        New platform · Sprint 0
                    </div>
                </div>
            </div>
            <div class="font-mono text-[11px] text-ink-soft uppercase tracking-widest">
                Laravel {{ app()->version() }}
            </div>
        </div>
    </header>

    {{-- Main hero panel --}}
    <main class="flex-1 bg-toco-silver-2">
        <section class="mx-auto max-w-[1100px] px-6 py-16">
            <div class="text-toco-red font-mono text-[11px] tracking-[0.2em] uppercase font-bold">
                Toco Japan — Sprint 2 preview
            </div>
            <h1 class="mt-3 text-4xl md:text-5xl font-extrabold tracking-tight text-ink">
                Quality Japanese vehicles, exported worldwide.
            </h1>
            <p class="mt-4 max-w-2xl text-base text-ink-soft leading-relaxed">
                Find your next vehicle below — pick a make to see the available models, then refine
                by year and body type. The full v5 homepage lands in Sprint 3.
            </p>

            <div class="mt-8 grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">
                <div class="bg-white border border-line rounded-md p-5">
                    <h2 class="font-bold text-toco-navy text-lg">Latest stock</h2>
                    <p class="text-sm text-ink-soft mt-1">{{ \App\Models\Vehicle::published()->count() }} vehicles currently listed for export.</p>
                    <a href="{{ route('vehicles.index') }}" class="inline-block mt-4 text-sm font-semibold text-toco-red hover:underline">Browse all vehicles →</a>
                </div>
                <x-search-widget
                    :makes="\App\Models\Make::where('is_active', true)->orderBy('name')->get(['id','slug','name'])"
                    :body-types="\App\Models\BodyType::where('is_active', true)->orderBy('name')->get(['id','slug','name'])"
                />
            </div>

            {{-- Palette swatches --}}
            <div class="mt-10 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                @foreach ([
                    ['toco-red',        '#E30613', 'Brand red'],
                    ['toco-red-deep',   '#B3000D', 'Red deep'],
                    ['toco-navy',       '#1F2356', 'Brand navy'],
                    ['toco-navy-deep',  '#10143A', 'Navy deep'],
                    ['toco-silver',     '#E6E7E8', 'Silver'],
                    ['toco-silver-2',   '#F4F5F7', 'Silver 2'],
                    ['ink',             '#0E1130', 'Ink'],
                ] as $swatch)
                    <figure class="bg-white border border-line">
                        <div class="h-20" style="background-color: {{ $swatch[1] }}"></div>
                        <figcaption class="px-3 py-2">
                            <div class="text-[12px] font-bold text-ink leading-tight">{{ $swatch[2] }}</div>
                            <div class="font-mono text-[10px] text-ink-soft tracking-wider">{{ $swatch[1] }}</div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            {{-- Build receipt --}}
            <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-white border border-line p-5">
                    <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-toco-red font-bold">
                        Toolchain
                    </div>
                    <ul class="mt-2 text-sm text-ink-soft space-y-1">
                        <li>PHP {{ PHP_VERSION }}</li>
                        <li>Laravel {{ app()->version() }}</li>
                        <li>Tailwind v4 · Vite</li>
                    </ul>
                </div>
                <div class="bg-white border border-line p-5">
                    <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-toco-red font-bold">
                        Fonts
                    </div>
                    <ul class="mt-2 text-sm text-ink-soft space-y-1">
                        <li class="font-sans">Montserrat — body</li>
                        <li class="font-mono">JetBrains Mono — labels</li>
                    </ul>
                </div>
                <div class="bg-white border border-line p-5">
                    <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-toco-red font-bold">
                        Next
                    </div>
                    <ul class="mt-2 text-sm text-ink-soft space-y-1">
                        <li>Sprint 1 · schema + auth</li>
                        <li>Sprint 1 · Filament admin</li>
                        <li>Sprint 1 · roles &amp; seeders</li>
                    </ul>
                </div>
            </div>
        </section>
    </main>

    {{-- Footer --}}
    <footer class="bg-toco-navy text-white">
        <div class="mx-auto max-w-[1440px] px-6 py-6 flex items-center justify-between">
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase opacity-70">
                © {{ date('Y') }} Toco International (Pvt) Ltd
            </div>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase opacity-70">
                Build · {{ config('app.env') }}
            </div>
        </div>
    </footer>
</body>
</html>
