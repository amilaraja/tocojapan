{{-- Listing "Quick view" modal body — HTML fragment injected via x-html by the
     modal in vehicles/index. Keep it layout-free and self-contained. --}}
@php
    ['gallery' => $photoUrls, 'thumb' => $thumbUrls] = $vehicle->photoSet();
    if ($photoUrls->isEmpty()) {
        $photoUrls = $thumbUrls = collect(['/img/v5/car-'.((($vehicle->id % 4) + 1)).'.jpg']);
    }
    $isSold = $vehicle->status === 'sold';
    $isDiscounted = $vehicle->isDiscounted();
    ['left' => $detailsLeft, 'right' => $detailsRight] = $vehicle->specDetails();
    $featureSchema = config('vehicle_features');
    // LC proforma eligibility (the calculator's "LC payment accepted" box).
    $proformaEligible = app(\App\Services\ProformaInvoiceService::class)->vehicleError($vehicle) === null;
    // CIF calculator (same cifCalc() as the detail page). Countries load lazily
    // from cif.destinations; `auto` recalculates on port change and carries
    // the chosen port to the next vehicle in the modal.
    $calcable = \App\Support\CifDestinations::calculable($vehicle) && (float) $vehicle->effectivePriceFob() > 0;
    $cifConfig = $calcable
        ? \App\Support\CifDestinations::calcConfig($vehicle, $destPort ?? null) + ['countriesUrl' => route('cif.destinations'), 'auto' => true]
        : null;
@endphp

<div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] gap-5 lg:gap-6"
     @if ($calcable) x-data="cifCalc({{ \Illuminate\Support\Js::from($cifConfig) }})" @endif>
    {{-- Gallery --}}
    <div x-data="{ i: 0, n: {{ $photoUrls->count() }} }" class="min-w-0">
        <div class="relative aspect-[4/3] bg-toco-silver-2 overflow-hidden">
            @foreach ($photoUrls as $k => $url)
                <img src="{{ $url }}" alt="{{ $vehicle->title }} — photo {{ $k + 1 }}"
                     x-show="i === {{ $k }}" @if ($k > 0) x-cloak loading="lazy" @endif
                     width="1280" height="960" decoding="async"
                     class="absolute inset-0 w-full h-full object-cover {{ $isSold ? 'grayscale' : '' }}">
            @endforeach
            @if ($isSold)
                <span class="absolute top-2 left-2 bg-toco-red text-white text-[11px] font-bold uppercase tracking-widest px-2.5 py-1 font-mono rounded-sm shadow">Sold</span>
            @elseif ($vehicle->isReserved())
                <span class="absolute top-2 left-2 text-white text-[11px] font-bold uppercase tracking-widest px-2.5 py-1 font-mono rounded-sm shadow" style="background:#B45309">Under offer</span>
            @endif
            @if ($photoUrls->count() > 1)
                <button type="button" @click="i = (i - 1 + n) % n" aria-label="Previous photo"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 grid place-items-center bg-white/90 hover:bg-white text-toco-navy shadow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18 9 12l6-6"/></svg>
                </button>
                <button type="button" @click="i = (i + 1) % n" aria-label="Next photo"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 grid place-items-center bg-white/90 hover:bg-white text-toco-navy shadow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                </button>
                <span class="absolute bottom-2 right-2 bg-black/60 text-white font-mono text-[11px] px-2 py-0.5" x-text="(i + 1) + ' / ' + n">1 / {{ $photoUrls->count() }}</span>
            @endif
        </div>
        @if ($thumbUrls->count() > 1)
            <div class="mt-2 flex gap-1.5 overflow-x-auto pb-1">
                @foreach ($thumbUrls as $k => $url)
                    <button type="button" @click="i = {{ $k }}" aria-label="Show photo {{ $k + 1 }}"
                            :class="i === {{ $k }} ? 'ring-2 ring-toco-red' : 'opacity-70 hover:opacity-100'"
                            class="shrink-0 w-16 h-12 bg-toco-silver-2 overflow-hidden">
                        <img src="{{ $url }}" alt="" loading="lazy" width="300" height="225" class="w-full h-full object-cover">
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Vehicle options sit under the thumbnails to use the gallery column's spare height --}}
        @if (! empty($vehicle->features))
            <div class="mt-4 pt-3 border-t border-line">
                <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-2">Vehicle options</p>
                <div class="flex flex-wrap gap-1.5 text-[11px] font-mono uppercase tracking-wider text-ink-soft">
                    @foreach ($vehicle->features as $group => $items)
                        @if (is_array($items))
                            @foreach ($items as $item)
                                @if ($item)
                                    <span class="px-2 py-0.5 border border-line rounded-sm" title="{{ $featureSchema[$group]['label'] ?? '' }}">{{ $item }}</span>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($calcable)
            {{-- Targeted by id, not x-ref: this sits inside the gallery's own x-data,
                 so a ref here would not be visible to the "Change" link's scope. --}}
            <div id="qv-cif-{{ $vehicle->id }}" class="mt-4 pt-3 border-t border-line scroll-mt-16">
                @include('partials.cif-calculator', ['vehicle' => $vehicle, 'proformaEligible' => $proformaEligible])
                <p x-show="loading" x-cloak class="mt-2 font-mono text-[11px] uppercase tracking-widest text-ink-soft">Calculating…</p>
            </div>
        @endif
    </div>

    {{-- Summary --}}
    <div class="min-w-0">
        <div class="flex items-center gap-3 mb-1">
            @if ($vehicle->ref_no)
                <span class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Ref no. {{ $vehicle->ref_no }}</span>
            @endif
            @if ($vehicle->stock_no)
                <span class="font-mono text-[10px] uppercase tracking-widest font-bold text-toco-red">#{{ $vehicle->stock_no }}</span>
            @endif
        </div>
        <h2 id="quick-view-title" class="font-extrabold text-toco-navy text-xl leading-tight">{{ $vehicle->title }}</h2>

        <div class="mt-3 flex items-end gap-5 flex-wrap">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Car price (FOB)</p>
                @if ($vehicle->price_on_request)
                    <p class="font-extrabold text-toco-red text-2xl leading-none mt-0.5">On request</p>
                @elseif ($isDiscounted)
                    <p class="font-mono text-[12px] text-ink-soft line-through leading-tight mt-0.5">@money($vehicle->price_fob)</p>
                    <p class="font-extrabold text-toco-red text-2xl leading-none mt-0.5">@money($vehicle->price_fob_discount)</p>
                @else
                    <p class="font-extrabold text-toco-red text-2xl leading-none mt-0.5">@money($vehicle->price_fob)</p>
                @endif
            </div>
            @if ($calcable)
                {{-- Follows the calculator below: saved-port CIF first, then the
                     live total (port + ticked add-ons) once recalculated. --}}
                <div>
                    <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">
                        CIF <span x-text="result?.port?.name || (touched ? '' : initialPortName) || 'to your port'">{{ ($destPort ?? null)?->name ?? 'to your port' }}</span>
                    </p>
                    <p class="font-bold text-toco-navy text-lg leading-none mt-0.5 tabular-nums"
                       x-show="result || (! touched && initialCif > 0)" x-text="fmt(result ? grandTotal() : initialCif)">
                        @if ($destPort ?? null)
                            @cif($vehicle, $destPort)
                        @endif
                    </p>
                    <p x-show="loading" x-cloak class="font-mono text-[11px] text-ink-soft leading-none mt-1">Calculating…</p>
                    <button type="button"
                            @click="const el = document.getElementById('qv-cif-{{ $vehicle->id }}'); el?.scrollIntoView({ behavior: 'smooth', block: 'start' }); el?.querySelector('select')?.focus({ preventScroll: true })"
                            class="mt-1 inline-flex items-center gap-1 font-mono text-[10px] uppercase tracking-widest font-bold text-toco-red hover:underline">
                        <span x-text="(result || (! touched && initialCif > 0)) ? 'Change country / port' : 'Calculate CIF'">{{ ($destPort ?? null) ? 'Change country / port' : 'Calculate CIF' }}</span>
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                </div>
            @endif
        </div>

        {{-- Buying options — same actions as the detail page's price card --}}
        <div class="mt-4 border border-line bg-toco-silver-2/40 p-3 sm:p-4">
            <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-2">Buying options</p>
            @if ($vehicle->isSupplierStock() && ! $isSold)
                <p class="text-[12px] text-ink-soft mb-2 leading-tight">Partner stock in Japan — availability and final price are confirmed with your quote.</p>
            @endif
            <div class="space-y-2">
                @include('partials.vehicle-buy-actions', ['vehicle' => $vehicle, 'quoteHref' => route('vehicles.show', $vehicle->slug).'#quote-form', 'portExpr' => $calcable ? 'portId' : null])
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-x-4 text-[13px]">
            @foreach ([$detailsLeft, $detailsRight] as $col)
                <dl class="divide-y divide-line">
                    @foreach ($col as [$label, $value])
                        <div class="py-1 flex items-baseline justify-between gap-2">
                            <dt class="font-mono text-[9px] uppercase tracking-widest text-ink-soft shrink-0">{{ $label }}</dt>
                            <dd class="font-semibold text-ink text-right break-words min-w-0">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-5 pt-4 border-t border-line flex justify-end">
    <a href="{{ route('vehicles.show', $vehicle->slug) }}"
       class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 py-2.5 border-2 border-toco-navy text-toco-navy font-bold text-sm uppercase tracking-wider hover:bg-toco-navy hover:text-white transition">
        View full details
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
</div>
