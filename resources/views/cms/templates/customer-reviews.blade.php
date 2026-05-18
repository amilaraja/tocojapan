<x-layouts.cms :page="$page">
    @push('head')
        <script type="application/ld+json">
        {!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'AutoDealer',
            'name' => config('app.name', 'Toco Japan'),
            'url' => url('/'),
            'aggregateRating' => ($reviewCount ?? 0) > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => $avgRating,
                'reviewCount' => $reviewCount,
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
            'review' => $testimonials->map(fn ($r) => array_filter([
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => $r->stars,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ],
                'author' => ['@type' => 'Person', 'name' => $r->name ?: 'Verified customer'],
                'reviewBody' => $r->quote ?: null,
                'datePublished' => optional($r->created_at)->toDateString(),
            ]))->values()->all(),
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1280px] mx-auto px-6 py-12 md:py-16">
            @if (! empty($page->data['kicker']))
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $page->data['kicker'] }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">
                {{ $page->data['headline'] ?? $page->title }}
            </h1>
            @if (! empty($page->data['intro']))
                <div class="prose prose-invert max-w-2xl mt-3 text-white/80">{!! $page->data['intro'] !!}</div>
            @endif
        </div>
    </section>

    <section class="bg-surface">
        <div class="max-w-[1280px] mx-auto px-6 py-12">
            @if ($testimonials->isEmpty())
                <p class="text-center text-ink-soft py-16">No customer reviews have been published yet.</p>
            @else
                <p class="text-sm text-ink-soft mb-6">
                    Showing {{ $testimonials->firstItem() }}–{{ $testimonials->lastItem() }}
                    of {{ number_format($testimonials->total()) }} customer {{ \Illuminate\Support\Str::plural('review', $testimonials->total()) }}.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @foreach ($testimonials as $r)
                        @php
                            $displayName = $r->name ?: 'Verified customer';
                            $line2 = trim(($r->flag ? $r->flag.' ' : '').($r->country ?? ''));
                        @endphp
                        <figure class="bg-white border border-line rounded-sm overflow-hidden flex flex-col">
                            @if ($r->getPhotoUrl())
                                <div class="aspect-[4/3] bg-toco-silver-2 overflow-hidden">
                                    <img src="{{ $r->getPhotoUrl() }}" alt="Delivery: {{ $r->vehicle_label ?: $displayName }}" class="w-full h-full object-cover block" loading="lazy">
                                </div>
                            @endif
                            <figcaption class="p-4 flex flex-col gap-1.5 flex-1">
                                @if ($r->vehicle_label)
                                    <div class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">{{ $r->vehicle_label }}</div>
                                @endif
                                <div class="text-amber-500 text-sm tracking-[0.12em]">{{ str_repeat('★', $r->stars).str_repeat('☆', 5 - $r->stars) }}</div>
                                @if ($r->quote)
                                    <blockquote class="text-[13px] text-ink-soft leading-relaxed mt-1">“{{ $r->quote }}”</blockquote>
                                @endif
                                <div class="mt-auto pt-3">
                                    <div class="text-sm font-bold text-toco-navy leading-tight">{{ $displayName }}</div>
                                    @if ($line2 !== '')
                                        <div class="text-[11px] text-ink-soft mt-0.5">{!! $line2 !!}</div>
                                    @endif
                                </div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $testimonials->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </section>
</x-layouts.cms>
