@php
    $seoTitle = $post->seo_title ?: $post->title.' — Toco Japan';
    $seoDescription = $post->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: $post->body), 155);
@endphp

<x-layouts.site :title="$seoTitle" :description="$seoDescription">
    @push('head')
        <script type="application/ld+json">
        {!! json_encode([
            '@'.'context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $post->title,
            'datePublished' => optional($post->published_at)->toIso8601String(),
            'dateModified' => $post->updated_at->toIso8601String(),
            'image' => $post->getFeaturedUrl(),
            'author' => ['@type' => 'Organization', 'name' => config('app.name', 'Toco Japan')],
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name', 'Toco Japan')],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <article class="max-w-[800px] mx-auto px-6 py-10">
        <nav class="text-[11px] font-mono uppercase tracking-widest text-ink-soft mb-4">
            <a href="{{ route('news.index') }}" class="hover:text-toco-red">News</a>
            @if ($post->category)
                <span class="mx-1">/</span>
                <a href="{{ route('news.index', ['category' => $post->category->slug]) }}" class="hover:text-toco-red">{{ $post->category->name }}</a>
            @endif
        </nav>

        <h1 class="text-3xl md:text-4xl font-extrabold text-toco-navy leading-tight">{{ $post->title }}</h1>

        <div class="flex items-center gap-2 text-xs text-ink-soft mt-3">
            @if ($post->category)
                <span class="text-toco-red font-bold uppercase tracking-widest font-mono text-[10px]">{{ $post->category->name }}</span>
                <span>·</span>
            @endif
            <span>{{ optional($post->published_at)->format('F j, Y') }}</span>
        </div>

        @if ($post->getFeaturedUrl())
            <div class="aspect-[16/9] bg-toco-silver-2 rounded-sm overflow-hidden mt-6">
                <img src="{{ $post->getFeaturedUrl() }}" alt="{{ $post->title }}" class="w-full h-full object-cover">
            </div>
        @endif

        @if ($post->excerpt)
            <p class="text-lg text-ink-soft leading-relaxed mt-6 font-medium">{{ $post->excerpt }}</p>
        @endif

        <div class="prose max-w-none mt-6">
            {!! $post->body !!}
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="bg-surface border-t border-line">
            <div class="max-w-[1200px] mx-auto px-6 py-12">
                <h2 class="text-xl font-extrabold text-toco-navy mb-5">More news</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    @foreach ($related as $r)
                        <article class="bg-white border border-line rounded-sm overflow-hidden hover:border-toco-red transition">
                            <a href="{{ route('news.show', $r->slug) }}" class="block aspect-[16/10] bg-toco-silver-2 overflow-hidden">
                                @if ($r->getFeaturedUrl())
                                    <img src="{{ $r->getFeaturedUrl() }}" alt="{{ $r->title }}" class="w-full h-full object-cover" loading="lazy">
                                @endif
                            </a>
                            <div class="p-4">
                                <div class="text-[10px] font-mono uppercase tracking-widest text-ink-soft">{{ optional($r->published_at)->format('M j, Y') }}</div>
                                <h3 class="font-bold text-toco-navy text-sm mt-1 leading-snug">
                                    <a href="{{ route('news.show', $r->slug) }}" class="hover:text-toco-red">{{ $r->title }}</a>
                                </h3>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.site>
