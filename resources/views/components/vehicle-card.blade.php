@props(['vehicle'])

@php
    $photo = $vehicle->getFirstMediaUrl('photos');
    $isNew = $vehicle->published_at && $vehicle->published_at->gt(now()->subDays(14));
    $fallbackPhoto = '/img/v5/car-'.((($vehicle->id % 4) + 1)).'.jpg';
    $isFavorited = in_array($vehicle->id, $favoritedIds ?? [], true);
@endphp

<div class="relative group bg-white border border-line hover:border-toco-navy transition">
    <a href="{{ route('vehicles.show', $vehicle->slug) }}" class="block">
        <div class="relative aspect-[4/3] bg-toco-silver-2 overflow-hidden">
            <img src="{{ $photo ?: $fallbackPhoto }}" alt="{{ $vehicle->title }}" class="w-full h-full object-cover transition group-hover:scale-[1.02]">
            @if ($isNew)
                <span class="absolute top-2 left-2 bg-toco-red text-white text-[10px] font-bold uppercase tracking-widest px-2 py-1 font-mono">New</span>
            @endif
        </div>
        <div class="p-3">
            <p class="font-mono text-[10px] tracking-widest uppercase text-ink-soft">
                {{ $vehicle->year_first_reg }} · {{ number_format((int) $vehicle->mileage_km) }}km · {{ strtoupper((string) $vehicle->transmission) }}
            </p>
            <h3 class="font-bold text-toco-navy mt-1 leading-tight line-clamp-2">{{ $vehicle->title }}</h3>
            <div class="mt-3 flex items-end justify-between">
                <div>
                    <p class="font-mono text-[10px] tracking-widest uppercase text-ink-soft">FOB Yokohama</p>
                    <p class="font-extrabold text-toco-red text-lg leading-none mt-0.5">
                        @if ($vehicle->price_on_request)
                            On request
                        @else
                            ${{ number_format((float) $vehicle->price_fob) }}
                        @endif
                    </p>
                </div>
                <span class="text-[12px] font-bold text-toco-navy group-hover:text-toco-red inline-flex items-center gap-1">
                    View <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
                </span>
            </div>
        </div>
    </a>

    {{-- Favorite toggle (real form, separate from the link) --}}
    <form method="POST" action="{{ route('favorites.toggle', $vehicle->slug) }}" class="absolute top-2 right-2">
        @auth @csrf @endauth
        <button type="{{ Auth::check() ? 'submit' : 'button' }}"
            @guest onclick="window.location='{{ route('login') }}'" @endguest
            aria-label="{{ $isFavorited ? 'Remove from favorites' : 'Save to favorites' }}"
            class="w-8 h-8 grid place-items-center rounded-full bg-white/90 border border-line hover:border-toco-red transition
                {{ $isFavorited ? 'text-toco-red' : 'text-ink-soft hover:text-toco-red' }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.5-7 10-7 10Z"/></svg>
        </button>
    </form>
</div>
