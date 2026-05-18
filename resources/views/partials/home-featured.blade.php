@php
    $showStockCounts = app(\App\Settings\GeneralSettings::class)->show_stock_counts;
@endphp
<section class="bg-surface mt-16">
    <div class="max-w-[1600px] mx-auto px-6 2xl:px-8">
        {{-- Section heading --}}
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-6">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">Latest stock</p>
                <h2 class="text-2xl md:text-3xl font-extrabold text-toco-navy mt-1 leading-tight">Featured vehicles</h2>
                <p class="text-ink-soft text-sm mt-1">{{ number_format($totalPublished) }} vehicles currently listed for export.</p>
            </div>
            <a href="{{ route('vehicles.index') }}" class="text-sm font-bold text-toco-red hover:text-toco-red-deep inline-flex items-center gap-1">
                View all stock <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[240px_minmax(0,1fr)_240px] gap-6">
            {{-- Left column: Browse by Make + Popular by country --}}
            <div class="hidden lg:flex flex-col gap-6">
                <aside class="bg-white border border-line rounded-sm">
                    <div class="p-3 border-b border-line">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Browse by</p>
                        <h3 class="font-bold text-toco-navy">Make</h3>
                    </div>
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

                @php
                    $popularCountries = [
                        ['popular-usa', 'United States', 'us'],
                        ['popular-uk', 'United Kingdom', 'sh'],
                        ['popular-zambia', 'Zambia', 'za'],
                        ['popular-tanzania', 'Tanzania', 'tz'],
                        ['popular-uganda', 'Uganda', 'ug'],
                        ['popular-mozambique', 'Mozambique', 'mz'],
                        ['popular-drcongo', 'D.R. Congo', 'cd'],
                        ['popular-zimbabwe', 'Zimbabwe', 'zw'],
                        ['popular-bangladesh', 'Bangladesh', 'bd'],
                        ['popular-pakistan', 'Pakistan', 'pk'],
                        ['popular-mongolia', 'Mongolia', 'mn'],
                        ['popular-sri-lanka', 'Sri Lanka', 'lk'],
                        ['popular-canada', 'Canada', 'ca'],
                        ['popular-new-zealand', 'New Zealand', 'nz'],
                        ['popular-australia', 'Australia', 'au'],
                    ];
                @endphp
                <aside class="bg-white border border-line rounded-sm">
                    <div class="p-3 border-b border-line">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Popular by</p>
                        <h3 class="font-bold text-toco-navy">Country</h3>
                    </div>
                    <ul class="text-[13px]">
                        @foreach ($popularCountries as [$slug, $label, $cc])
                            <li>
                                <a href="{{ url('/'.$slug) }}" class="flex items-center gap-2.5 px-3 py-2 hover:bg-toco-silver-2 border-b border-line/60 last:border-b-0">
                                    <img src="/legacy/uploads/2023/11/{{ $cc }}.svg" alt="" width="22" height="16" class="shrink-0 rounded-[2px] border border-line" loading="lazy">
                                    <span class="flex-1 font-semibold">{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </aside>
            </div>

            {{-- Vehicle grid --}}
            <div>
                @if ($featured->isEmpty())
                    <div class="bg-white border border-line rounded-sm p-8 text-center text-ink-soft">No published vehicles yet.</div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach ($featured as $vehicle)
                            <x-vehicle-card :vehicle="$vehicle" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Browse by Body Type sidebar --}}
            <aside class="bg-white border border-line rounded-sm hidden lg:block">
                <div class="p-3 border-b border-line">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Browse by</p>
                    <h3 class="font-bold text-toco-navy">Body type</h3>
                </div>
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
        </div>
    </div>
</section>
