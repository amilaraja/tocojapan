@php
    $seoTitle = ($activeCategory ? $activeCategory->name.' — ' : '').'News & updates — Toco Japan';
    $seoDescription = 'Company news, shipping updates and Japanese used-car market notes from Toco Japan.';
@endphp

<x-layouts.site :title="$seoTitle" :description="$seoDescription">
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1200px] mx-auto px-6 py-12 md:py-16">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">Newsroom</p>
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">
                {{ $activeCategory->name ?? 'News & updates' }}
            </h1>
            <p class="text-white/75 mt-3 max-w-2xl">Company news, shipping updates and notes from the Japanese used-car market.</p>
        </div>
    </section>

    <section class="max-w-[1200px] mx-auto px-6 py-10">
        @if ($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-8">
                <a href="{{ route('news.index') }}"
                   class="px-3 py-1.5 rounded-sm text-xs font-bold uppercase tracking-widest border transition {{ ! $activeCategory ? 'bg-toco-red text-white border-toco-red' : 'bg-white text-toco-navy border-line hover:border-toco-red' }}">
                    All
                </a>
                @foreach ($categories as $cat)
                    @continue($cat->posts_count === 0)
                    <a href="{{ route('news.index', ['category' => $cat->slug]) }}"
                       class="px-3 py-1.5 rounded-sm text-xs font-bold uppercase tracking-widest border transition {{ $activeCategory && $activeCategory->id === $cat->id ? 'bg-toco-red text-white border-toco-red' : 'bg-white text-toco-navy border-line hover:border-toco-red' }}">
                        {{ $cat->name }} ({{ $cat->posts_count }})
                    </a>
                @endforeach
            </div>
        @endif

        @if ($posts->isEmpty())
            <div class="bg-toco-silver-2 border border-line rounded-sm px-6 py-16 text-center text-ink-soft">
                <p class="font-mono text-[11px] uppercase tracking-widest">No news posts yet</p>
                <p class="text-sm mt-2">Check back soon for company updates.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($posts as $post)
                    <article class="bg-white border border-line rounded-sm overflow-hidden flex flex-col hover:border-toco-red transition">
                        <a href="{{ route('news.show', $post->slug) }}" class="block aspect-[16/10] bg-toco-silver-2 overflow-hidden">
                            @if ($post->getFeaturedUrl())
                                <img src="{{ $post->getFeaturedUrl() }}" alt="{{ $post->title }}" class="w-full h-full object-cover" loading="lazy">
                            @endif
                        </a>
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-center gap-2 text-[10px] font-mono uppercase tracking-widest text-ink-soft">
                                @if ($post->category)
                                    <span class="text-toco-red font-bold">{{ $post->category->name }}</span>
                                    <span>·</span>
                                @endif
                                <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
                            </div>
                            <h2 class="font-bold text-toco-navy mt-1.5 leading-snug">
                                <a href="{{ route('news.show', $post->slug) }}" class="hover:text-toco-red">{{ $post->title }}</a>
                            </h2>
                            @if ($post->excerpt)
                                <p class="text-[13px] text-ink-soft mt-2 leading-relaxed line-clamp-3">{{ $post->excerpt }}</p>
                            @endif
                            <a href="{{ route('news.show', $post->slug) }}" class="mt-auto pt-3 text-xs font-bold uppercase tracking-widest text-toco-red hover:text-toco-red-deep">Read more →</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $posts->links() }}
            </div>
        @endif
    </section>
</x-layouts.site>
