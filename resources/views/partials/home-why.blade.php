<section id="why-toco" class="bg-toco-silver-2 mt-16">
    <div class="max-w-[1440px] mx-auto px-6 py-16">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">Why Toco</p>
            <h2 class="text-2xl md:text-3xl font-extrabold text-toco-navy mt-1">A trusted partner from auction to your port.</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                ['num' => '01', 'title' => 'Deep stock', 'body' => 'Direct access to over 69,000 cars across Japan auctions plus our own owner stock.', 'icon' => 'M3 7h18M3 12h18M3 17h18'],
                ['num' => '02', 'title' => 'Trusted process', 'body' => 'JEVIC + JAAI inspection, transparent paperwork, and TT banking that just works.', 'icon' => 'M12 2 4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6Z'],
                ['num' => '03', 'title' => 'Worldwide shipping', 'body' => 'Roll-on/roll-off and container shipping to every major port across Africa, Caribbean, Pacific.', 'icon' => 'M3 18h18M5 18 7 8h10l2 10M9 8V6h6v2'],
                ['num' => '04', 'title' => 'Real support', 'body' => 'Talk to people, not bots. We answer in English, French, and Japanese — usually within hours.', 'icon' => 'M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9 8.5 8.5 0 0 1 8.5 8.5Z'],
            ] as $f)
                <div class="bg-white border border-line p-5 rounded-sm">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 grid place-items-center bg-toco-red text-white rounded-sm">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="{{ $f['icon'] }}"/></svg>
                        </span>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">No.{{ $f['num'] }}</span>
                    </div>
                    <h3 class="font-extrabold text-toco-navy text-lg mt-4 leading-tight">{{ $f['title'] }}</h3>
                    <p class="text-sm text-ink-soft mt-2 leading-relaxed">{{ $f['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- How it works strip --}}
<section id="how-it-works" class="bg-surface">
    <div class="max-w-[1440px] mx-auto px-6 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-8 items-start">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">How it works</p>
                <h2 class="text-2xl md:text-3xl font-extrabold text-toco-navy mt-1 leading-tight">Four steps from browse to delivery.</h2>
                <p class="text-sm text-ink-soft mt-3">No surprises, no hidden costs — just a clear path from picking your car to driving it home.</p>
            </div>
            <ol class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ([
                    ['01', 'Pick a car', 'Browse our stock or send us a target spec.'],
                    ['02', 'Get a CIF quote', 'Country, port and currency picked — we cost it out.'],
                    ['03', 'Pay & inspect', 'TT to a Japanese bank. JEVIC/JAAI inspect on your behalf.'],
                    ['04', 'Ship & receive', 'Containerised or RoRo. We handle docs end to end.'],
                ] as $step)
                    <li class="bg-white border border-line p-4 rounded-sm relative">
                        <span class="font-mono text-[28px] font-extrabold text-toco-red leading-none block">{{ $step[0] }}</span>
                        <h3 class="font-bold text-toco-navy mt-3">{{ $step[1] }}</h3>
                        <p class="text-[13px] text-ink-soft mt-1">{{ $step[2] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
