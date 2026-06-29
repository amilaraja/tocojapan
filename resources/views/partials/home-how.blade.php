@php
    use App\Support\HowToBuyIcons;

    $howSteps = $content['how_steps'] ?? [
        [
            'num' => '01',
            'icon' => 'search',
            'title' => 'Search & Order',
            'body' => 'Get a price quote',
            'buttons' => [
                ['label' => 'Inquiry Now', 'url' => '/contact', 'icon' => 'inquiry', 'style' => 'solid'],
                ['label' => 'Buy Now', 'url' => '/vehicles', 'icon' => 'buy_now', 'style' => 'solid'],
            ],
        ],
        [
            'num' => '02',
            'icon' => 'payment',
            'title' => 'Payment',
            'body' => 'Pay securely with PayPal or telegraphic bank transfer to our Japanese bank.',
            'buttons' => [
                ['label' => 'PayPal (Online)', 'url' => '/vehicles', 'icon' => 'paypal', 'style' => 'outline'],
                ['label' => 'Bank Transfer', 'url' => route('cms.page', 'bank-details'), 'icon' => 'bank', 'style' => 'outline'],
            ],
        ],
        [
            'num' => '03',
            'icon' => 'shipment',
            'title' => 'Car Shipment',
            'body' => 'RoRo or container — we book vessel space and handle all export docs from Yokohama.',
        ],
        [
            'num' => '04',
            'icon' => 'clearing',
            'title' => 'Clearing',
            'body' => 'You receive originals (B/L, invoice, export certificate, JEVIC) by courier to clear customs.',
        ],
        [
            'num' => '05',
            'icon' => 'received',
            'title' => 'Car Received',
            'body' => 'Pick up your vehicle from the destination port and you\'re ready to drive.',
        ],
    ];
    $howKicker = $content['how_intro_kicker'] ?? 'How to buy';
    $howHeadline = $content['how_intro_headline'] ?? 'Simple steps to get your vehicle from TOCO';
    $howBody = $content['how_intro_body'] ?? null;

    // Resolve a CMS-uploaded image path (public disk) to a usable URL.
    $howImg = function ($path) {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        return (str_starts_with($path, '/') || str_starts_with($path, 'http'))
            ? $path
            : '/storage/'.$path;
    };
@endphp

<section id="how-to-buy" class="bg-surface">
    <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-14">
        <div class="mb-8">
            <h2 class="text-2xl md:text-[28px] font-extrabold text-toco-navy uppercase tracking-tight leading-tight">{{ $howKicker }}</h2>
            <p class="text-ink-soft text-sm mt-1">{{ $howHeadline }}</p>
            @if ($howBody)
                <p class="text-ink-soft text-sm mt-1 max-w-2xl">{{ $howBody }}</p>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr_auto_1fr] gap-4 lg:gap-2 items-stretch">
            @foreach ($howSteps as $i => $step)
                @php
                    $iconSvg = HowToBuyIcons::svg($step['icon'] ?? null);
                    $iconImg = $howImg($step['icon_image'] ?? null);
                    $buttons = is_array($step['buttons'] ?? null) ? $step['buttons'] : [];
                @endphp
                <div class="relative bg-white border border-line rounded-sm p-5 pt-7 flex flex-col h-full shadow-[0_1px_2px_rgba(16,20,58,.04)]">
                    {{-- Number badge --}}
                    <span class="absolute -top-3 left-4 font-mono font-extrabold text-base tracking-widest text-white px-3 py-1.5 rounded-sm shadow bg-toco-navy">
                        {{ $step['num'] ?? sprintf('%02d', $i + 1) }}
                    </span>

                    {{-- Icon: uploaded image if provided, otherwise the built-in glyph on a soft tile --}}
                    @if ($iconImg)
                        <img src="{{ $iconImg }}" alt="{{ $step['title'] ?? '' }}" width="64" height="64" loading="lazy"
                             class="w-16 h-16 object-contain mt-2 mx-auto">
                    @else
                        <div class="w-14 h-14 grid place-items-center bg-toco-silver-2 text-toco-navy ring-1 ring-line rounded-sm mt-2 mx-auto">
                            @if ($iconSvg)
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $iconSvg !!}</svg>
                            @endif
                        </div>
                    @endif

                    {{-- Title + underline rule --}}
                    <h3 class="font-extrabold text-toco-navy text-center text-sm uppercase tracking-widest mt-4">{{ $step['title'] ?? '' }}</h3>
                    <div class="mx-auto mt-2 h-0.5 w-12 bg-toco-navy/15" aria-hidden="true"></div>

                    {{-- Body text (always shown when present) --}}
                    @if (! empty($step['body']))
                        <p class="text-[12px] text-ink-soft text-center mt-3 leading-snug">{{ $step['body'] }}</p>
                    @endif

                    {{-- Buttons (cards 1 & 2) --}}
                    @if (! empty($buttons))
                        <div class="flex flex-col gap-2 mt-4 mt-auto pt-4">
                            @foreach ($buttons as $btn)
                                @php
                                    $btnIcon = HowToBuyIcons::svg($btn['icon'] ?? null);
                                    $btnImg = $howImg($btn['icon_image'] ?? null);
                                    $solid = ($btn['style'] ?? 'solid') === 'solid';
                                @endphp
                                <a href="{{ $btn['url'] ?? '#' }}"
                                   class="font-bold uppercase tracking-widest text-[10px] px-3 py-2 rounded-sm inline-flex items-center justify-center gap-1.5 transition
                                          {{ $solid ? 'bg-toco-navy text-white hover:bg-toco-navy-deep' : 'bg-white text-toco-navy border border-line hover:border-toco-navy hover:bg-toco-silver-2' }}">
                                    @if ($btnImg)
                                        <img src="{{ $btnImg }}" alt="" width="16" height="16" class="w-4 h-4 object-contain shrink-0">
                                    @elseif ($btnIcon)
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">{!! $btnIcon !!}</svg>
                                    @endif
                                    <span>{{ $btn['label'] ?? '' }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Chevron arrow between cards (lg+ only — on mobile the cards stack) --}}
                @if (! $loop->last)
                    <div class="hidden lg:flex items-center justify-center text-toco-navy/30" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 6 6 6-6 6"/>
                        </svg>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="mt-6 text-right">
            <a href="{{ \Illuminate\Support\Facades\Route::has('cms.page') ? route('cms.page', 'how-to-buy-cars-and-other-vehicles') : '#' }}"
               class="text-sm font-bold text-toco-navy hover:text-toco-red inline-flex items-center gap-1">
                How to buy Step by Step <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>
