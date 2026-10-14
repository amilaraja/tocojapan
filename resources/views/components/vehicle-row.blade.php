@props(['vehicle'])

{{--
    Listing row for /vehicles (client design 2026-10-14, docs/OCT13/product-card.png):
    photo + ref no. | title, key specs, spec table, features | price, CIF total, Inquiry.
    Styles are plain CSS in the vr- namespace below (the Tailwind build is prebuilt on the server).
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
    $place = $vehicle->location ? trim(explode(',', $vehicle->location)[0]) : null;

    $stats = array_filter([
        ['Mileage', $vehicle->mileage_km ? number_format((int) $vehicle->mileage_km).' km' : null],
        ['Year', $year ? $year.($month ? '/'.$month : '') : null],
        ['Engine', $vehicle->engine_cc ? number_format((int) $vehicle->engine_cc).'cc' : null],
        ['Trans.', $trans],
    ], fn ($s) => filled($s[1]));

    $specs = [
        ['Model code', $vehicle->model_code],
        ['Steering', $vehicle->steering_side ? ucfirst($vehicle->steering_side) : null],
        ['Fuel', $vehicle->fuel ? ucfirst($vehicle->fuel) : null],
        ['Seats', $vehicle->seats],
        ['Chassis no.', $vehicle->chassisNumberRedacted()],
        ['Color', $vehicle->exterior_color ? ucfirst($vehicle->exterior_color) : null],
        ['Drive', $vehicle->drive ? strtoupper($vehicle->drive) : null],
        ['Doors', $vehicle->doors],
    ];

    $tags = [];
    if (is_array($vehicle->features)) {
        foreach ($vehicle->features as $items) {
            foreach ((array) $items as $label) {
                if (is_string($label) && $label !== '' && count($tags) < 6) {
                    $tags[] = $label;
                }
            }
        }
    }

    $showCif = ! $vehicle->price_on_request && ($destPort ?? null) && $vehicle->m3 > 0 && $vehicle->effectivePriceFob() > 0;
@endphp

@once
@push('head')
<style>
    .vr{display:grid;grid-template-columns:1fr;gap:14px;background:#fff;border:1px solid var(--color-line);padding:14px;transition:border-color .15s}
    .vr:hover{border-color:var(--color-toco-navy)}
    .vr-media{position:relative}
    .vr-photo{display:block;position:relative;aspect-ratio:4/3;background:var(--color-toco-silver-2);overflow:hidden}
    .vr-photo img{width:100%;height:100%;object-fit:cover;transition:transform .2s}
    .vr:hover .vr-photo img{transform:scale(1.02)}
    .vr-badge{position:absolute;top:8px;right:8px;color:#fff;font:700 10px/1 ui-monospace,monospace;text-transform:uppercase;letter-spacing:.1em;padding:5px 8px;border-radius:2px}
    .vr-save{top:auto;bottom:8px}
    .vr-fav{position:absolute;top:8px;left:8px}
    .vr-fav button{width:32px;height:32px;display:grid;place-items:center;border-radius:4px;background:rgba(255,255,255,.92);border:1px solid var(--color-line);color:var(--color-ink-soft);cursor:pointer}
    .vr-fav button:hover,.vr-fav button.on{color:var(--color-toco-red);border-color:var(--color-toco-red)}
    .vr-ref{margin-top:8px;background:var(--color-toco-silver);text-align:center;font-weight:700;font-size:14px;color:var(--color-ink);padding:4px 6px}
    .vr-main{min-width:0}
    .vr-title{font-size:18px;font-weight:800;line-height:1.25;color:var(--color-toco-navy)}
    .vr-title a:hover{color:var(--color-toco-red)}
    .vr-stats{display:flex;flex-wrap:wrap;margin-top:10px}
    .vr-stat{padding:2px 16px;text-align:center;border-left:1px solid var(--color-line)}
    .vr-stat:first-child{border-left:0;padding-left:0}
    .vr-stat dt{font-size:13px;color:var(--color-ink-soft)}
    .vr-stat dd{font-size:16px;font-weight:700;color:var(--color-ink);white-space:nowrap;display:flex;align-items:center;gap:6px;justify-content:center}
    .vr-stat dd img{width:20px;height:14px;box-shadow:0 0 0 1px var(--color-line)}
    .vr-specs{display:grid;grid-template-columns:repeat(2,max-content minmax(0,1fr));gap:1px;margin-top:12px;background:var(--color-line);border:1px solid var(--color-line);font-size:13px}
    .vr-specs dt,.vr-specs dd{padding:5px 8px;color:var(--color-ink);overflow-wrap:anywhere}
    .vr-specs dt{background:var(--color-toco-silver-2);white-space:nowrap}
    .vr-specs dd{background:#fff}
    .vr-feat{margin-top:12px;font-size:13px;color:var(--color-ink)}
    .vr-feat span+span:before{content:"/";color:var(--color-muted);margin:0 7px}
    .vr-side{display:flex;flex-direction:column;gap:8px}
    .vr-line{display:flex;align-items:baseline;justify-content:space-between;gap:10px}
    .vr-lbl{font-size:13px;color:var(--color-ink-soft)}
    .vr-price{font-size:24px;font-weight:800;color:#d83b01;line-height:1.1}
    .vr-was{font-size:12px;color:var(--color-muted);text-decoration:line-through;text-align:right}
    .vr-total{border-top:1px solid var(--color-line);padding-top:8px}
    .vr-total .vr-price{font-size:20px;font-weight:700}
    .vr-note{font-size:12px;color:var(--color-ink-soft);text-align:right}
    .vr-note b{color:var(--color-ink)}
    .vr-cta{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;background:#ef7c0e;color:#fff;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:.06em;padding:11px 12px;border-radius:3px;box-shadow:0 1px 2px rgba(0,0,0,.15)}
    .vr-cta:hover{background:#d4690a;color:#fff}
    .vr-quick{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:none;border:1px solid #c4c8d0;border-radius:3px;padding:10px 12px;cursor:pointer;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:.06em;color:var(--color-toco-navy)}
    .vr-quick:hover{border-color:var(--color-toco-navy)}
    .vr-sold .vr-photo img{filter:grayscale(1)}
    @media (min-width:768px){
        .vr-specs{grid-template-columns:repeat(4,max-content minmax(0,1fr))}
    }
    @media (min-width:640px){
        .vr{grid-template-columns:190px minmax(0,1fr)}
        .vr-side{grid-column:1 / -1}
    }
    @media (min-width:1100px){
        .vr{grid-template-columns:190px minmax(0,1fr) 210px;gap:18px}
        .vr-side{grid-column:auto}
    }
    @media (max-width:639px){
        .vr-stats{display:grid;grid-template-columns:repeat(3,1fr);row-gap:8px}
        .vr-stat{padding:0 6px}
        .vr-stat:nth-child(3n+1){border-left:0;padding-left:0}
        .vr-stat dd{font-size:14px}
        .vr-specs{font-size:12px}
        .vr-specs dt,.vr-specs dd{padding:4px 6px}
    }
</style>
@endpush
@endonce

<article class="vr {{ $isSold ? 'vr-sold' : '' }}">
    {{-- Photo, favourite, ref no. --}}
    <div class="vr-media">
        <a href="{{ $url }}" class="vr-photo">
            <img src="{{ $photo ?: $fallbackPhoto }}" alt="{{ $vehicle->title }}"
                 @if ($photo && $photo2x)
                 srcset="{{ $photo }} 560w, {{ $photo2x }} 1280w"
                 sizes="(min-width:640px) 190px, 100vw"
                 @endif
                 width="560" height="420" decoding="async" loading="lazy">
            @if ($isSold)
                <span class="vr-badge" style="background:var(--color-toco-red)">Sold</span>
            @elseif ($vehicle->isReserved())
                <span class="vr-badge" style="background:#B45309">Under offer</span>
            @elseif ($vehicle->is_featured)
                <span class="vr-badge" style="background:#c2410c">Hot deal</span>
            @elseif ($isNew)
                <span class="vr-badge" style="background:#047857">New</span>
            @endif
            @if ($isDiscounted)
                <span class="vr-badge vr-save" style="background:var(--color-toco-navy)">
                    Save {{ (int) round((((float) $vehicle->price_fob - (float) $vehicle->price_fob_discount) / (float) $vehicle->price_fob) * 100) }}%
                </span>
            @endif
        </a>
        <form method="POST" action="{{ route('favorites.toggle', $vehicle->slug) }}" class="vr-fav">
            @auth @csrf @endauth
            <button type="{{ Auth::check() ? 'submit' : 'button' }}" class="{{ $isFavorited ? 'on' : '' }}"
                @guest onclick="window.location='{{ route('login') }}'" @endguest
                aria-label="{{ $isFavorited ? 'Remove from favorites' : 'Save to favorites' }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.5-7 10-7 10Z"/></svg>
            </button>
        </form>
        @if ($ref)
            <div class="vr-ref">Ref No. {{ $ref }}</div>
        @endif
    </div>

    {{-- Title, key specs, spec table, features --}}
    <div class="vr-main">
        <h3 class="vr-title"><a href="{{ $url }}">{{ $vehicle->title }}</a></h3>

        <dl class="vr-stats">
            @foreach ($stats as [$label, $value])
                <div class="vr-stat"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
            @if ($place)
                <div class="vr-stat"><dt>Location</dt><dd><img src="/img/flags/jp.svg" alt="" width="20" height="14">{{ $place }}</dd></div>
            @endif
        </dl>

        <dl class="vr-specs">
            @foreach ($specs as [$label, $value])
                <dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd>
            @endforeach
        </dl>

        @if (! empty($tags))
            <p class="vr-feat">
                @foreach ($tags as $t)<span>{{ $t }}</span>@endforeach
            </p>
        @endif
    </div>

    {{-- Price, CIF total, Inquiry --}}
    <div class="vr-side">
        <div class="vr-line">
            <span class="vr-lbl">Price</span>
            @if ($vehicle->price_on_request)
                <span class="vr-price" style="font-size:18px">On request</span>
            @elseif ($isDiscounted)
                <span><span class="vr-was" style="display:block">@money($vehicle->price_fob)</span><span class="vr-price">@money($vehicle->price_fob_discount)</span></span>
            @else
                <span class="vr-price">@money($vehicle->price_fob)</span>
            @endif
        </div>
        <div class="vr-total">
            @if ($showCif)
                <div class="vr-line">
                    <span class="vr-lbl">Total Price</span>
                    <span class="vr-price">@cif($vehicle, $destPort)</span>
                </div>
                <p class="vr-note">CIF to <b>{{ $destPort->name }}</b></p>
            @elseif (! $vehicle->price_on_request)
                <p class="vr-note" style="text-align:left">Choose your port above to see the total price delivered.</p>
            @endif
        </div>
        @unless ($isSold)
            <a href="{{ $url }}#quote-form" class="vr-cta">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                Inquiry
            </a>
        @endunless
        <button type="button" x-data class="vr-quick"
                data-quick-view="{{ route('vehicles.quick-view', $vehicle->slug) }}" data-href="{{ $url }}"
                @click="$dispatch('quick-view', { url: $el.dataset.quickView })">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
            Quick view
        </button>
    </div>
</article>
