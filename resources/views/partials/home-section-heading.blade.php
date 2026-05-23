@php
    // Reusable kicker + heading + optional view-all link.
    $icon = $icon ?? null;
    $kicker = $kicker ?? null;
    $sublabel = $sublabel ?? null;
    $viewAllUrl = $viewAllUrl ?? null;
    $viewAllLabel = $viewAllLabel ?? 'View all';
@endphp
<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-2 mb-4">
    <div>
        @if ($kicker)
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $kicker }}</p>
        @endif
        <h2 class="flex items-center gap-2 text-2xl md:text-[28px] font-extrabold text-toco-navy mt-1 leading-tight">
            @if ($icon === 'fire')
                <svg width="22" height="22" viewBox="0 0 24 24" fill="#E30613" aria-hidden="true"><path d="M12 2s4 4 4 8a4 4 0 0 1-8 0c0-1 .3-2 1-3 0 2-1 3-1 5a5 5 0 1 0 10 0c0-5-6-10-6-10zm-2 14a2 2 0 1 0 4 0c0-1-1-2-1-3-1 1-3 1-3 3z"/></svg>
            @elseif ($icon === 'clock')
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10143A" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            @elseif ($icon === 'star')
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#E30613" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
            @endif
            <span>{{ $heading }}</span>
        </h2>
        @if ($sublabel)
            <p class="text-ink-soft text-sm mt-1">{{ $sublabel }}</p>
        @endif
    </div>
    @if ($viewAllUrl)
        <a href="{{ $viewAllUrl }}" class="text-sm font-bold text-toco-red hover:text-toco-red-deep inline-flex items-center gap-1">
            {{ $viewAllLabel }} <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
        </a>
    @endif
</div>
