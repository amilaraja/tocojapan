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

            {{-- Feature tags (inline, sourced from features JSON; capped at 3) --}}
            @php
                $tags = [];
                if (is_array($vehicle->features)) {
                    foreach ($vehicle->features as $group => $items) {
                        if (! is_array($items)) {
                            continue;
                        }
                        foreach ($items as $label) {
                            if ($label && count($tags) < 3) {
                                $tags[] = $label;
                            }
                        }
                        if (count($tags) >= 3) {
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
        </div>
    </div>

    {{-- Favorite heart (absolute on photo, mobile-friendly) --}}
    @auth
    <form method="POST" action="{{ route('favorites.toggle', $vehicle->slug) }}" class="hidden">
        @csrf
    </form>
    @endauth
</article>
