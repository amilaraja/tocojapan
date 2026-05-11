@php
    $stats = $content['stats'] ?? [];
    $statsLeadA = $stats['lead_a'] ?? 'By the numbers,';
    $statsLeadB = $stats['lead_b'] ?? 'since 2009.';
    $items = $stats['items'] ?? [
        ['n' => '14,200', 'unit' => '+',  'label' => 'Units shipped'],
        ['n' => '90',     'unit' => '+',  'label' => 'Countries served'],
        ['n' => '8,412',  'unit' => '',   'label' => 'Cars in stock'],
        ['n' => '4.9',    'unit' => '/5', 'label' => '2,800+ reviews'],
    ];
@endphp

<section id="stats" class="bg-toco-navy text-white relative overflow-hidden">
    {{-- subtle diagonal slash, matches v5 StatsSlash --}}
    <svg class="absolute inset-0 w-full h-full pointer-events-none opacity-[0.06]" viewBox="0 0 1440 320" preserveAspectRatio="xMaxYMid slice" aria-hidden="true">
        <path d="M1100 0 L1440 0 L1440 320 L900 320 Z" fill="#E30613"/>
    </svg>

    <div class="relative max-w-[1600px] mx-auto px-6 2xl:px-8 py-16 md:py-20">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-8 items-center">
            <div class="col-span-2 md:col-span-3 lg:col-span-1">
                <h3 class="text-2xl md:text-3xl font-extrabold leading-tight tracking-tight">
                    {{ $statsLeadA }} <span class="text-toco-red">{{ $statsLeadB }}</span>
                </h3>
            </div>
            @foreach ($items as $stat)
                <div>
                    <div class="text-4xl md:text-5xl font-extrabold leading-none tracking-tight">
                        {{ $stat['n'] ?? '' }}<span class="text-[0.5em] opacity-60 ml-0.5">{{ $stat['unit'] ?? '' }}</span>
                    </div>
                    <div class="mt-2 font-mono text-[11px] uppercase tracking-[0.14em] text-white/70">{{ $stat['label'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
