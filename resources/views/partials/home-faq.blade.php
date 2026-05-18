@php
    $faq = $content['faq'] ?? [];
    $faqKicker = $faq['kicker'] ?? 'Buyer FAQ';
    $faqHeadline = $faq['headline'] ?? 'Questions before you import.';
    $faqBody = $faq['body'] ?? 'The answers most first-time importers ask before placing an order. Need anything else? Message us — we reply same business day.';

    $faqDefaults = [
        [
            'q' => 'How much does it cost to ship a car from Japan?',
            'a' => "Shipping is quoted CIF (cost, insurance, freight) to your nearest port. Typical RoRo freight from Yokohama is USD 800–1,800 for a sedan and USD 1,500–3,500 for an SUV or van, depending on destination. Use our CIF calculator on any vehicle page for an exact number to your port.",
        ],
        [
            'q' => 'Right-hand drive or left-hand drive — which can I import?',
            'a' => "Japan is a RHD market so 95% of our stock is right-hand drive. We do source LHD units (Hummer, US-market Chevrolet, etc.) on request. Check your country's regulations before ordering — some markets (e.g. Vietnam, Thailand for new imports) restrict RHD.",
        ],
        [
            'q' => 'What is the difference between FOB and CIF?',
            'a' => "FOB (Free On Board) is the price at Yokohama port. CIF adds ocean freight + marine insurance to your destination port. We display FOB on every vehicle; CIF is auto-calculated for your country with the calculator on the vehicle page.",
        ],
        [
            'q' => 'How long does shipping take?',
            'a' => "Once payment clears, the vehicle is booked on the next available RoRo vessel — usually 1–4 weeks to load. Ocean transit is 2–6 weeks depending on destination (e.g. ~14 days to Colombo, ~21 days to Mombasa, ~35 days to Caribbean ports). Total delivery is typically 4–10 weeks door-to-port.",
        ],
        [
            'q' => 'How do I pay?',
            'a' => "Telegraphic Transfer (TT) to our Japanese bank is standard for the full CIF amount. PayPal is also enabled for smaller deposits and Buy-Now reservations. We do not accept cash. Bank details are issued on your pro-forma invoice once the order is confirmed.",
        ],
        [
            'q' => 'Can I inspect the car before shipping?',
            'a' => 'Yes. We provide JEVIC / JAAI third-party inspection certificates on request (USD 150–300). For Sri Lanka, JEVIC inspection is mandatory and included in CIF by default. We also send extra photos and a video walk-around free of charge.',
        ],
        [
            'q' => 'Do you sell to my country?',
            'a' => "We ship to 90+ countries including Sri Lanka, Kenya, Tanzania, Uganda, Zambia, Mozambique, Mauritius, Trinidad, Jamaica, Guyana, Pakistan, Bangladesh, Myanmar and many more. If your port isn't listed in the CIF calculator, message us — we'll quote it manually.",
        ],
        [
            'q' => 'What age limits apply to imports?',
            'a' => 'Each country sets its own age cap (often the vehicle must be under 5, 7, 10 or 15 years old). Filter by year on the listing page to match your destination. We can also flag age-eligible stock by country — just tell us where you are importing to.',
        ],
    ];

    $faqItems = $faq['items'] ?? $faqDefaults;
    $faqItems = collect($faqItems)
        ->map(fn ($i) => ['q' => trim($i['q'] ?? ''), 'a' => trim($i['a'] ?? '')])
        ->filter(fn ($i) => $i['q'] !== '' && $i['a'] !== '')
        ->values();
@endphp

@if ($faqItems->isNotEmpty())
    @push('head')
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqItems->map(fn ($i) => [
                '@type' => 'Question',
                'name' => $i['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']],
            ])->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <section id="faq" class="bg-surface border-t border-line">
        <div class="max-w-[1100px] mx-auto px-6 py-16">
            <div class="max-w-2xl mb-8">
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $faqKicker }}</p>
                <h2 class="text-2xl md:text-3xl font-extrabold text-toco-navy mt-1 leading-tight">{{ $faqHeadline }}</h2>
                <p class="text-sm text-ink-soft mt-3">{{ $faqBody }}</p>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                @foreach ($faqItems as $i)
                    <details class="group bg-white border border-line rounded-sm">
                        <summary class="cursor-pointer flex items-start justify-between gap-3 px-4 py-3 font-semibold text-toco-navy text-sm hover:bg-toco-silver-2 list-none">
                            <span class="leading-snug">{{ $i['q'] }}</span>
                            <span class="text-toco-red font-mono text-lg group-open:rotate-45 transition shrink-0 leading-none mt-0.5">+</span>
                        </summary>
                        <div class="px-4 pb-4 pt-0 text-[13px] text-ink-soft whitespace-pre-line leading-relaxed">{{ $i['a'] }}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
