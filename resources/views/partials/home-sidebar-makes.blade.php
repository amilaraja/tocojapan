@props(['makesWithCounts', 'showStockCounts'])
<aside class="bg-white border border-line rounded-sm overflow-hidden">
    @include('partials.home-sidebar-header', ['kicker' => 'Search by', 'title' => 'Make'])
    <ul class="text-[13px]">
        @foreach ($makesWithCounts as $make)
            <li>
                <a href="{{ route('vehicles.index') }}?make={{ $make->slug }}" class="flex items-center gap-2 px-3 py-2 hover:bg-toco-silver-2 border-b border-line/60 last:border-b-0">
                    <span class="w-7 h-7 grid place-items-center shrink-0">
                        @if ($make->getLogoUrl())
                            <img src="{{ $make->getLogoUrl() }}" alt="" class="max-w-full max-h-full object-contain" loading="lazy">
                        @else
                            <span class="w-6 h-6 grid place-items-center bg-toco-silver-2 text-toco-navy text-[10px] font-bold rounded-sm">{{ mb_strtoupper(mb_substr($make->name, 0, 1)) }}</span>
                        @endif
                    </span>
                    <span class="flex-1 font-semibold capitalize">{{ mb_strtolower($make->name) }}</span>
                    @if ($showStockCounts)
                        <span class="font-mono text-[10px] text-ink-soft tabular-nums">{{ $make->published_count }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
    <a href="{{ route('vehicles.index') }}" class="block px-3 py-2.5 text-[12px] font-bold uppercase tracking-widest text-toco-red border-t border-line">All makes →</a>
</aside>
