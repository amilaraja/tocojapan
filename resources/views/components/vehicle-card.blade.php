@props(['vehicle', 'priority' => false, 'stockLabel' => true])

{{--
    Grid card (home hot deals / featured, recently viewed, favourites, country pages).
    Same look as the /vehicles listing row (x-vehicle-row, client design 2026-10-14) in the old card's footprint.
    Plain CSS in the vc- namespace below (the Tailwind build is prebuilt on the server).
--}}

@php
    $photo = $vehicle->cardPhotoUrl();
    $photo2x = $vehicle->galleryPhotoUrl();
    $url = route('vehicles.show', $vehicle->slug);
    $isSold = $vehicle->status === 'sold';
    $isNew = ! $isSold && $vehicle->isNewArrival();
    $fallbackPhoto = '/img/v5/car-'.((($vehicle->id % 4) + 1)).'.jpg';
    $isFavorited = in_array($vehicle->id, $favoritedIds ?? [], true);
    $isDiscounted = $vehicle->isDiscounted();
    $ref = $vehicle->ref_no ?: $vehicle->stock_no;

    $year = $vehicle->year_first_reg ?: $vehicle->manufacture_year;
    $month = $vehicle->year_first_reg ? $vehicle->registration_month : $vehicle->manufacture_month;
    $trans = match ($vehicle->transmission) {
        'automatic' => 'AT',
        'manual' => 'MT',
        null, '' => null,
        default => strtoupper(substr((string) $vehicle->transmission, 0, 3)),
    };
    $stats = [
        ['Year', $year ? $year.($month ? '/'.$month : '') : null],
        ['Mileage', $vehicle->mileage_km !== null ? number_format((int) $vehicle->mileage_km).' km' : null],
        ['Engine', $vehicle->engine_cc ? number_format((int) $vehicle->engine_cc).'cc' : null],
        ['Trans.', $trans],
    ];
    $showCif = ! $vehicle->price_on_request && $vehicle->price_fob > 0 && ($destPort ?? null) && $vehicle->m3 > 0;
@endphp

@once
@push('head')
<style>
    .vc{position:relative;display:flex;flex-direction:column;height:100%;background:#fff;border:1px solid var(--color-line);transition:border-color .15s}
    .vc:hover{border-color:var(--color-toco-navy)}
    .vc-photo{display:block;position:relative;aspect-ratio:4/3;background:var(--color-toco-silver-2);overflow:hidden}
    .vc-photo img{width:100%;height:100%;object-fit:cover;transition:transform .2s}
    .vc:hover .vc-photo img{transform:scale(1.02)}
    .vc-badge{position:absolute;top:8px;left:8px;color:#fff;font:700 10px/1 ui-monospace,monospace;text-transform:uppercase;letter-spacing:.1em;padding:5px 8px;border-radius:2px}
    .vc-save{top:auto;left:auto;bottom:8px;right:8px}
    .vc-fav{position:absolute;top:8px;right:8px;z-index:11}
    .vc-fav button{width:30px;height:30px;display:grid;place-items:center;border-radius:4px;background:rgba(255,255,255,.92);border:1px solid var(--color-line);color:var(--color-ink-soft);cursor:pointer}
    .vc-fav button:hover,.vc-fav button.on{color:var(--color-toco-red);border-color:var(--color-toco-red)}
    .vc-ref{display:inline-block;align-self:flex-start;background:var(--color-toco-silver);font-weight:700;font-size:11px;line-height:1;color:var(--color-ink);padding:3px 6px;margin-bottom:4px}
    .vc-stock{position:absolute;left:8px;bottom:8px;color:#fff;font:800 10px/1 ui-sans-serif,system-ui,sans-serif;text-transform:uppercase;letter-spacing:.05em;padding:5px 7px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,.25)}
    .vc-body{display:flex;flex-direction:column;flex:1;padding:8px 10px 9px}
    .vc-title{font-size:14px;font-weight:800;line-height:1.25;color:var(--color-toco-navy);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .vc-title a:hover{color:var(--color-toco-red)}
    .vc-stats{display:grid;grid-template-columns:1fr 1fr;column-gap:8px;margin-top:5px;border-top:1px solid var(--color-line);border-bottom:1px solid var(--color-line);padding:2px 0;font-size:12px;line-height:1.3;font-weight:700;color:var(--color-ink)}
    .vc-stats span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vc-stats span:nth-child(even){text-align:right}
    .vc-buy{display:flex;align-items:flex-end;justify-content:space-between;gap:8px;margin-top:auto;padding-top:5px}
    .vc-lbl{font-size:11px;line-height:1.2;color:var(--color-ink-soft)}
    .vc-price{display:block;font-size:18px;font-weight:800;color:#d83b01;line-height:1.1;white-space:nowrap}
    .vc-was{font-size:11px;color:var(--color-muted);text-decoration:line-through;margin-left:4px}
    .vc-cif{font-size:11px;line-height:1.3;color:var(--color-ink-soft);margin-top:3px}
    .vc-cif b{color:#d83b01;font-size:13px}
    .vc-cta{display:flex;align-items:center;gap:4px;flex-shrink:0;padding:6px 8px;border-radius:3px;background:#ef7c0e;color:#fff;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
    .vc-cta:hover{background:#d4690a;color:#fff}
    .vc-sold .vc-photo img{filter:grayscale(1)}
</style>
@endpush
@endonce

<div class="vc {{ $isSold ? 'vc-sold' : '' }}">
    <a href="{{ $url }}" class="vc-photo">
        <img src="{{ $photo ?: $fallbackPhoto }}" alt="{{ $vehicle->title }}"
             @if ($photo && $photo2x)
             srcset="{{ $photo }} 560w, {{ $photo2x }} 1280w"
             sizes="(min-width:1280px) 300px, (min-width:768px) 33vw, (min-width:640px) 50vw, 50vw"
             @endif
             width="560" height="420" decoding="async"
             loading="{{ $priority ? 'eager' : 'lazy' }}"
             fetchpriority="{{ $priority ? 'high' : 'auto' }}">
        @if ($isSold)
            <span class="vc-badge" style="background:var(--color-toco-red)">Sold</span>
        @elseif ($vehicle->isReserved())
            <span class="vc-badge" style="background:#B45309">Under offer</span>
        @elseif ($vehicle->is_featured)
            <span class="vc-badge" style="background:#c2410c">Hot deal</span>
        @elseif ($isNew)
            <span class="vc-badge" style="background:#047857">New</span>
        @endif
        @if ($stockLabel)<span class="vc-stock" style="background:{{ $vehicle->isSupplierStock() ? 'var(--color-toco-navy)' : 'var(--color-toco-red)' }}">{{ $vehicle->stockLabel() }}</span>@endif
        @if ($isDiscounted)
            <span class="vc-badge vc-save" style="background:var(--color-toco-navy)">
                Save {{ (int) round((((float) $vehicle->price_fob - (float) $vehicle->price_fob_discount) / (float) $vehicle->price_fob) * 100) }}%
            </span>
        @endif
    </a>

    {{-- Favorite toggle (real form, separate from the link) --}}
    <form method="POST" action="{{ route('favorites.toggle', $vehicle->slug) }}" class="vc-fav">
        @auth @csrf @endauth
        <button type="{{ Auth::check() ? 'submit' : 'button' }}" class="{{ $isFavorited ? 'on' : '' }}"
            @guest onclick="window.location='{{ route('login') }}'" @endguest
            aria-label="{{ $isFavorited ? 'Remove from favorites' : 'Save to favorites' }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.5-7 10-7 10Z"/></svg>
        </button>
    </form>

    <div class="vc-body">
        @if ($ref)
            <span class="vc-ref">Ref No. {{ $ref }}</span>
        @endif
        <h3 class="vc-title"><a href="{{ $url }}">{{ $vehicle->title }}</a></h3>

        @if (collect($stats)->filter(fn ($st) => filled($st[1]))->isNotEmpty())
            <p class="vc-stats">
                @foreach ($stats as [$label, $value])
                    @if (filled($value))<span title="{{ $label }}">{{ $value }}</span>@endif
                @endforeach
            </p>
        @endif

        <div class="vc-buy">
            <div style="min-width:0">
                @if ($isDiscounted && ! $vehicle->price_on_request)
                    <span class="vc-lbl vc-was" style="margin-left:0">@money($vehicle->price_fob)</span>
                @else
                    <span class="vc-lbl">Price</span>
                @endif
                @if ($vehicle->price_on_request)
                    <span class="vc-price" style="font-size:15px">On request</span>
                @else
                    <span class="vc-price">@money($isDiscounted ? $vehicle->price_fob_discount : $vehicle->price_fob)</span>
                @endif
            </div>
            @unless ($isSold)
                <a href="{{ $url }}#quote-form" class="vc-cta">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    Inquiry
                </a>
            @endunless
        </div>
        @if ($showCif)
            <p class="vc-cif">CIF {{ $destPort->name }}: <b>@cif($vehicle, $destPort)</b></p>
        @endif
    </div>
</div>
