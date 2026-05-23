@props(['bodyTypesWithCounts', 'showStockCounts'])
<aside class="bg-white border border-line rounded-sm overflow-hidden">
    @include('partials.home-sidebar-header', ['kicker' => 'Search by', 'title' => 'Body type'])
    <ul class="text-[13px]">
        @foreach ($bodyTypesWithCounts as $bt)
            <li>
                <a href="{{ route('vehicles.index') }}?body_type={{ $bt->slug }}" class="flex items-center gap-2 px-3 py-2 hover:bg-toco-silver-2 border-b border-line/60 last:border-b-0">
                    <span class="w-7 h-7 grid place-items-center text-toco-navy shrink-0">
                        @if ($bt->getLogoUrl())
                            <img src="{{ $bt->getLogoUrl() }}" alt="" class="max-w-full max-h-full object-contain" loading="lazy">
                        @else
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="9" width="18" height="8" rx="2"/><circle cx="7.5" cy="18" r="1.5"/><circle cx="16.5" cy="18" r="1.5"/></svg>
                        @endif
                    </span>
                    <span class="flex-1 font-semibold">{{ $bt->name }}</span>
                    @if ($showStockCounts)
                        <span class="font-mono text-[10px] text-ink-soft tabular-nums">{{ $bt->published_count }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</aside>
