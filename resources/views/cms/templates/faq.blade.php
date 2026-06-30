<x-layouts.cms :page="$page">
    @php
        $faqItems = collect($page->data['groups'] ?? [])
            ->flatMap(fn ($g) => collect($g['items'] ?? [])->filter(fn ($i) => ! empty($i['question']) && ! empty($i['answer'])))
            ->map(fn ($i) => [
                '@type' => 'Question',
                'name' => $i['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['answer']],
            ])->values()->all();
    @endphp
    @if (! empty($faqItems))
        @push('head')
            <script type="application/ld+json">
            {!! json_encode([
                '@'.'context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqItems,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
            </script>
        @endpush
    @endif

    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1100px] mx-auto px-6 py-12 md:py-16">
            @if (! empty($page->data['kicker']))
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $page->data['kicker'] }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">
                {{ $page->data['headline'] ?? $page->title }}
            </h1>
        </div>
    </section>

    <section class="max-w-[900px] mx-auto px-6 py-10 space-y-10">
        @foreach ($page->data['groups'] ?? [] as $group)
            <div>
                <h2 class="text-xl font-extrabold text-toco-navy">{{ $group['title'] ?? '' }}</h2>
                <div class="mt-4 divide-y divide-line bg-white border border-line rounded-sm">
                    @foreach ($group['items'] ?? [] as $item)
                        <details class="group">
                            <summary class="cursor-pointer flex items-center justify-between px-5 py-4 font-semibold text-toco-navy hover:bg-toco-silver-2">
                                <span>{{ $item['question'] ?? '' }}</span>
                                <span class="text-toco-red font-mono text-lg group-open:rotate-45 transition">+</span>
                            </summary>
                            <div class="px-5 pb-4 pt-0 text-sm text-ink-soft whitespace-pre-line leading-relaxed">{{ $item['answer'] ?? '' }}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
</x-layouts.cms>
