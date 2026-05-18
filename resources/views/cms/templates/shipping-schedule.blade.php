<x-layouts.cms :page="$page">
    @php
        $d = $page->data ?? [];
        $image = trim($d['image'] ?? '');
        $imageUrl = $image === ''
            ? null
            : (\Illuminate\Support\Str::startsWith($image, ['http://', 'https://', '/'])
                ? $image
                : \Illuminate\Support\Facades\Storage::disk('public')->url($image));
    @endphp

    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1100px] mx-auto px-6 py-12 md:py-16">
            @if (! empty($d['kicker']))
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $d['kicker'] }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">{{ $d['headline'] ?? $page->title }}</h1>
            @if (! empty($d['intro']))
                <div class="mt-4 text-white/80 max-w-2xl prose prose-invert">{!! $d['intro'] !!}</div>
            @endif
        </div>
    </section>

    <section class="max-w-[1200px] mx-auto px-6 py-10">
        @if ($imageUrl)
            <div class="bg-white border border-line rounded-sm overflow-hidden">
                <img src="{{ $imageUrl }}" alt="{{ $d['headline'] ?? $page->title }}" class="w-full h-auto block">
            </div>
        @else
            <div class="bg-toco-silver-2 border border-line rounded-sm px-6 py-16 text-center text-ink-soft">
                <p class="font-mono text-[11px] uppercase tracking-widest">No schedule image uploaded</p>
                <p class="text-sm mt-2">Upload one in admin → Pages → Shipping Schedule.</p>
            </div>
        @endif
    </section>
</x-layouts.cms>
