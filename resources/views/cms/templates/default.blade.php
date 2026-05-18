<x-layouts.cms :page="$page">
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

    <article class="max-w-[820px] mx-auto px-6 py-12">
        @if (! empty($page->data['body']))
            <div class="prose prose-lg max-w-none
                        prose-headings:text-toco-navy prose-headings:font-extrabold
                        prose-h2:text-2xl prose-h2:mt-3 prose-h2:mb-1.5
                        prose-h3:text-xl prose-h3:mt-6
                        prose-p:text-ink-soft prose-p:leading-relaxed prose-p:mt-1.5
                        prose-a:text-toco-red prose-a:font-semibold
                        prose-strong:text-toco-navy
                        prose-li:text-ink-soft prose-li:marker:text-toco-red
                        prose-img:rounded-lg prose-img:shadow-sm prose-img:w-full prose-img:mt-12 prose-img:mb-0">
                {!! $page->data['body'] !!}
            </div>
        @endif
    </article>
</x-layouts.cms>
