@php
    $whyDefault = [
        ['num' => '01', 'title' => 'Deep stock', 'body' => 'Direct access to over 69,000 cars across Japan auctions plus our own owner stock.'],
        ['num' => '02', 'title' => 'Trusted process', 'body' => 'JEVIC + JAAI inspection, transparent paperwork, and TT banking that just works.'],
        ['num' => '03', 'title' => 'Worldwide shipping', 'body' => 'Roll-on/roll-off and container shipping to every major port across Africa, Caribbean, Pacific.'],
        ['num' => '04', 'title' => 'Real support', 'body' => 'Talk to people, not bots. We answer in English, French, and Japanese — usually within hours.'],
    ];
    $why = $content['why_toco'] ?? $whyDefault;
    $whyKicker = $content['why_kicker'] ?? 'Why Toco';
    $whyHeadline = $content['why_headline'] ?? 'A trusted partner from auction to your port.';
@endphp

<section id="why-toco" class="bg-toco-silver-2 mt-16">
    <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-16">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $whyKicker }}</p>
            <h2 class="text-2xl md:text-3xl font-extrabold text-toco-navy mt-1">{{ $whyHeadline }}</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($why as $f)
                <div class="bg-white border border-line p-5 rounded-sm">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 grid place-items-center bg-toco-red text-white rounded-sm">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                        </span>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">No.{{ $f['num'] ?? '' }}</span>
                    </div>
                    <h3 class="font-extrabold text-toco-navy text-lg mt-4 leading-tight">{{ $f['title'] ?? '' }}</h3>
                    <p class="text-sm text-ink-soft mt-2 leading-relaxed">{{ $f['body'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
