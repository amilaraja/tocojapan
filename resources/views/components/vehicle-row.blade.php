@props(['vehicle'])

@php
    $photo = $vehicle->cardPhotoUrl();
    $photo2x = $vehicle->galleryPhotoUrl();
    $isSold = $vehicle->status === 'sold';
    $isNew = ! $isSold && $vehicle->isNewArrival();
    $fallbackPhoto = '/img/v5/car-'.((($vehicle->id % 4) + 1)).'.jpg';
    $isFavorited = in_array($vehicle->id, $favoritedIds ?? [], true);
    $isDiscounted = $vehicle->isDiscounted();
@endphp

<article class="bg-white border border-line hover:border-toco-navy transition group flex flex-col sm:flex-row {{ $isSold ? 'opacity-90' : '' }}">
    {{-- Photo --}}
    <a href="{{ route('vehicles.show', $vehicle->slug) }}" class="relative block shrink-0 w-full sm:w-[260px] md:w-[280px] aspect-[4/3] sm:aspect-auto sm:self-stretch bg-toco-silver-2 overflow-hidden">
        <img src="{{ $photo ?: $fallbackPhoto }}" alt="{{ $vehicle->title }}"
             @if ($photo && $photo2x)
             srcset="{{ $photo }} 560w, {{ $photo2x }} 1280w"
             sizes="(min-width:1024px) 280px, (min-width:640px) 260px, 50vw"
             @endif
             width="560" height="420" decoding="async" loading="lazy"
             class="w-full h-full object-cover transition group-hover:scale-[1.02] {{ $isSold ? 'grayscale' : '' }}">
        @if ($isSold)
            <span class="absolute top-2 left-2 bg-toco-red text-white text-[11px] font-bold uppercase tracking-widest px-2.5 py-1 font-mono rounded-sm shadow">Sold</span>
        @elseif ($vehicle->is_featured)
            <span class="absolute top-2 left-2 bg-orange-700 text-white text-[10px] font-bold uppercase tracking-widest px-2 py-1 font-mono">Hot Deal</span>
        @elseif ($isNew)
            <span class="absolute top-2 left-2 bg-emerald-700 text-white text-[10px] font-bold uppercase tracking-widest px-2 py-1 font-mono">New</span>
        @endif
        @if ($isDiscounted)
            <span class="absolute top-2 right-2 bg-toco-navy text-white text-[10px] font-bold uppercase tracking-widest px-2 py-1 font-mono rounded-sm">
                Save {{ (int) round((((float) $vehicle->price_fob - (float) $vehicle->price_fob_discount) / (float) $vehicle->price_fob) * 100) }}%
            </span>
        @endif
    </a>

    {{-- Content --}}
    <div class="flex-1 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 md:gap-6 p-4 md:p-5">
        <div class="min-w-0">
            <div class="flex items-center gap-3 mb-1">
                @if ($vehicle->ref_no)
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Ref no. {{ $vehicle->ref_no }}</span>
                @endif
                @if ($vehicle->stock_no)
                    <span class="font-mono text-[10px] uppercase tracking-widest font-bold text-toco-red">#{{ $vehicle->stock_no }}</span>
                @endif
            </div>
            <h3 class="font-extrabold text-toco-navy text-lg leading-tight">
                <a href="{{ route('vehicles.show', $vehicle->slug) }}" class="hover:text-toco-red transition">{{ $vehicle->title }}</a>
            </h3>

            <div class="mt-3 flex items-end gap-4 flex-wrap">
                <div>
                    <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Car price</p>
                    @if ($vehicle->price_on_request)
                        <p class="font-extrabold text-toco-red text-xl leading-none mt-0.5">On request</p>
                    @elseif ($isDiscounted)
                        <p class="font-mono text-[12px] text-ink-soft line-through leading-tight mt-0.5">@money($vehicle->price_fob)</p>
                        <p class="font-extrabold text-toco-red text-xl leading-none mt-0.5">@money($vehicle->price_fob_discount)</p>
                    @else
                        <p class="font-extrabold text-toco-red text-xl leading-none mt-0.5">@money($vehicle->price_fob)</p>
                    @endif
                </div>
                @if (! $vehicle->price_on_request && ($destPort ?? null) && $vehicle->m3 > 0 && $vehicle->effectivePriceFob() > 0)
                    <div>
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">CIF {{ $destPort->name }}</p>
                        <p class="font-bold text-toco-navy text-base leading-none mt-0.5">@cif($vehicle, $destPort)</p>
                    </div>
                @endif
            </div>

            {{-- Key details grid — fills the middle column with the identifying specs buyers scan for --}}
            @php
                $fmtDim = fn ($v) => ($v !== null && (float) $v > 0) ? rtrim(rtrim(number_format((float) $v / 100, 2, '.', ''), '0'), '.') : null;
                $dimParts = [$fmtDim($vehicle->length_cm), $fmtDim($vehicle->width_cm), $fmtDim($vehicle->height_cm)];
                $details = array_filter([
                    ['Model code', $vehicle->model_code],
                    ['Chassis no.', $vehicle->chassis_number],
                    ['Colour', $vehicle->exterior_color ? ucfirst($vehicle->exterior_color) : null],
                    ['Dimension', array_filter($dimParts) ? implode(' × ', array_map(fn ($p) => $p ?? '?', $dimParts)).' m' : null],
                    ['M3', $vehicle->m3 > 0 ? number_format((float) $vehicle->m3, 2) : null],
                    ['Location', $vehicle->location],
                ], fn ($d) => filled($d[1]));
            @endphp
            @if (! empty($details))
                <dl class="mt-3 grid grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1.5 text-[12px]">
                    @foreach ($details as [$label, $value])
                        <div class="min-w-0">
                            <dt class="font-mono text-[9px] uppercase tracking-widest text-ink-soft">{{ $label }}</dt>
                            <dd class="font-semibold text-ink truncate" title="{{ $value }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            {{-- Feature tags (inline, sourced from features JSON; capped at 5) --}}
            @php
                $tags = [];
                if (is_array($vehicle->features)) {
                    foreach ($vehicle->features as $group => $items) {
                        if (! is_array($items)) {
                            continue;
                        }
                        foreach ($items as $label) {
                            if ($label && count($tags) < 5) {
                                $tags[] = $label;
                            }
                        }
                        if (count($tags) >= 5) {
                            break;
                        }
                    }
                }
            @endphp
            @if (! empty($tags))
                <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] font-mono uppercase tracking-wider text-ink-soft">
                    @foreach ($tags as $t)
                        <span class="px-2 py-0.5 border border-line rounded-sm">{{ $t }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Spec block on the right --}}
        <div class="md:border-l md:border-line md:pl-6 grid grid-cols-3 md:grid-cols-1 gap-2 md:gap-1 text-[12px] md:min-w-[160px]">
            @php
                $rows = array_filter([
                    $vehicle->mileage_km ? ['Mileage', number_format((int) $vehicle->mileage_km).' km', $vehicle->mileage_km ? round($vehicle->mileage_km / 1.609).' mi' : null] : null,
                    $vehicle->engine_cc ? ['Engine', number_format((int) $vehicle->engine_cc).' cc', ucfirst((string) $vehicle->fuel)] : null,
                    $vehicle->transmission ? ['Trans', strtoupper(substr($vehicle->transmission, 0, 3)), strtoupper((string) $vehicle->drive)] : null,
                    ($vehicle->seats || $vehicle->doors) ? ['Seats / Doors', ($vehicle->seats ?? '–').' / '.($vehicle->doors ?? '–'), $vehicle->steering_side === 'right' ? 'RHD' : 'LHD'] : null,
                ]);
            @endphp
            @foreach ($rows as [$label, $main, $sub])
                <div class="md:flex md:items-baseline md:gap-2">
                    <span class="font-mono text-[9px] uppercase tracking-widest text-ink-soft block md:flex-1">{{ $label }}</span>
                    <span class="font-bold text-ink md:text-right">{{ $main }}</span>
                    @if ($sub)
                        <span class="block md:hidden text-[10px] text-ink-soft">{{ $sub }}</span>
                    @endif
                </div>
                @if ($sub)
                    <div class="hidden md:block text-[10px] text-ink-soft -mt-0.5 text-right">{{ $sub }}</div>
                @endif
            @endforeach
            <button type="button" x-data
                    data-quick-view="{{ route('vehicles.quick-view', $vehicle->slug) }}" data-href="{{ route('vehicles.show', $vehicle->slug) }}"
                    @click="$dispatch('quick-view', { url: $el.dataset.quickView })"
                    class="col-span-3 md:col-span-1 mt-1 md:mt-3 inline-flex items-center justify-center gap-1.5 px-3 py-2 border border-toco-navy text-toco-navy font-bold text-[11px] uppercase tracking-widest hover:bg-toco-navy hover:text-white transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                Quick view
            </button>
        </div>
    </div>

    {{-- Favorite heart (absolute on photo, mobile-friendly) --}}
    @auth
    <form method="POST" action="{{ route('favorites.toggle', $vehicle->slug) }}" class="hidden">
        @csrf
    </form>
    @endauth
</article>
