@php
    $howSteps = $content['how_steps'] ?? [
        ['num' => '01', 'title' => 'Pick a car',     'body' => 'Browse our stock or send us a target spec.'],
        ['num' => '02', 'title' => 'Get a CIF quote', 'body' => 'Country, port and currency picked — we cost it out.'],
        ['num' => '03', 'title' => 'Pay & inspect',  'body' => 'TT to a Japanese bank. JEVIC/JAAI inspect on your behalf.'],
        ['num' => '04', 'title' => 'Ship & receive', 'body' => 'Containerised or RoRo. We handle docs end to end.'],
    ];
    $howKicker = $content['how_intro_kicker'] ?? 'How it works';
    $howHeadline = $content['how_intro_headline'] ?? 'Four steps from browse to delivery.';
    $howBody = $content['how_intro_body'] ?? 'No surprises, no hidden costs — just a clear path from picking your car to driving it home.';
@endphp

<section id="how-it-works" class="bg-surface">
    <div class="max-w-[1280px] mx-auto px-6 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-8 items-start">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $howKicker }}</p>
                <h2 class="text-2xl md:text-3xl font-extrabold text-toco-navy mt-1 leading-tight">{{ $howHeadline }}</h2>
                <p class="text-sm text-ink-soft mt-3">{{ $howBody }}</p>
            </div>
            <ol class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($howSteps as $step)
                    <li class="bg-white border border-line p-4 rounded-sm relative">
                        <span class="font-mono text-[28px] font-extrabold text-toco-red leading-none block">{{ $step['num'] ?? '' }}</span>
                        <h3 class="font-bold text-toco-navy mt-3">{{ $step['title'] ?? '' }}</h3>
                        <p class="text-[13px] text-ink-soft mt-1">{{ $step['body'] ?? '' }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
