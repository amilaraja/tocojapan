@php
    $showStockCounts = app(\App\Settings\GeneralSettings::class)->show_stock_counts;
    $activeMake = ($filters['make'] ?? '') ? ($makes->firstWhere('slug', $filters['make'])?->name) : null;
    $activeBody = ($filters['body_type'] ?? '') ? \Illuminate\Support\Str::title(str_replace('-', ' ', $filters['body_type'])) : null;
    $activeModel = ($filters['vehicle_model'] ?? '') ? ($models->firstWhere('slug', $filters['vehicle_model'])?->name) : null;
    $facet = trim(($activeMake ?? '').' '.($activeBody ?? '')) ?: null;
    $title = $facet
        ? "Used Japanese {$facet} for export — Toco Japan"
        : 'Used Japanese cars for export — Toco Japan';
    $description = $facet
        ? number_format($vehicles->total()).' used '.$facet.' vehicles ready to ship from Japan. RHD/LHD, RoRo & container, FOB or CIF to your port.'
        : number_format($vehicles->total()).' used Japanese vehicles in stock — Toyota, Honda, Nissan, Mazda and more. FOB Japan, worldwide CIF.';

    // Helper: rebuild current querystring + override one param (for sort / page-size links)
    $qs = function (array $overrides) use ($filters) {
        $merged = array_filter(array_merge($filters, $overrides), fn ($v) => $v !== null && $v !== '');
        return http_build_query($merged);
    };

    // Country/port payload for the inline CIF calculator. Built here so the
    // Alpine x-data block can stay simple (no nested @json with array literals,
    // which Blade's directive parser mis-tokenises as ']' terminators).
    $destPayload = $destCountries->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'ports' => $c->ports->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
    ])->values();

    // Active filter chips — clicking removes that one filter
    $chipDefs = [
        'make' => $activeMake,
        'vehicle_model' => $activeModel,
        'body_type' => $activeBody,
        'q' => $filters['q'] ?? null,
        'transmission' => $filters['transmission'] ?? null,
        'fuel' => $filters['fuel'] ?? null,
        'steering' => ($filters['steering'] ?? null) ? ucfirst($filters['steering']).' hand drive' : null,
        'year_from' => ($filters['year_from'] ?? null) ? 'From '.$filters['year_from'] : null,
        'year_to' => ($filters['year_to'] ?? null) ? 'To '.$filters['year_to'] : null,
        'price_from' => ($filters['price_from'] ?? null) ? 'From $'.number_format((float) $filters['price_from']) : null,
        'price_to' => ($filters['price_to'] ?? null) ? 'To $'.number_format((float) $filters['price_to']) : null,
        'mileage_max' => ($filters['mileage_max'] ?? null) ? 'Mileage ≤ '.number_format((int) $filters['mileage_max']).' km' : null,
        'engine_from' => ($filters['engine_from'] ?? null) ? 'Engine ≥ '.$filters['engine_from'].' cc' : null,
        'engine_to' => ($filters['engine_to'] ?? null) ? 'Engine ≤ '.$filters['engine_to'].' cc' : null,
        'featured' => ($filters['featured'] ?? null) ? 'Hot deals' : null,
        'discounted' => ($filters['discounted'] ?? null) ? 'Discounted' : null,
        'new_only' => ($filters['new_only'] ?? null) ? 'New arrivals' : null,
    ];
@endphp

<x-layouts.site :title="$title" :description="$description">
    <section class="bg-toco-silver-2 py-6">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)] gap-6">

                {{-- ============ LEFT SIDEBAR ============ --}}
                <aside class="flex flex-col gap-4">
                    {{-- Search by Make --}}
                    <div class="bg-white border border-line rounded-sm overflow-hidden">
                        @include('partials.home-sidebar-header', ['kicker' => 'Search by', 'title' => 'Make'])
                        <ul class="text-[13px] max-h-[480px] overflow-y-auto">
                            @foreach ($makes as $make)
                                @php($selected = ($filters['make'] ?? '') === $make->slug)
                                <li>
                                    <a href="{{ route('vehicles.index').'?'.$qs(['make' => $selected ? null : $make->slug, 'vehicle_model' => null]) }}"
                                       class="flex items-center gap-2 px-3 py-2 border-b border-line/60 last:border-b-0 transition {{ $selected ? 'bg-toco-silver-2 text-toco-red' : 'hover:bg-toco-silver-2' }}">
                                        <span class="w-7 h-7 grid place-items-center shrink-0">
                                            @if ($make->getLogoUrl())
                                                <img src="{{ $make->getLogoUrl() }}" alt="" class="max-w-full max-h-full object-contain" loading="lazy">
                                            @else
                                                <span class="w-6 h-6 grid place-items-center bg-toco-silver-2 text-toco-navy text-[10px] font-bold rounded-sm">{{ mb_strtoupper(mb_substr($make->name, 0, 1)) }}</span>
                                            @endif
                                        </span>
                                        <span class="flex-1 font-semibold capitalize">{{ mb_strtolower($make->name) }}</span>
                                        @if ($showStockCounts && ($make->published_count ?? 0))
                                            <span class="font-mono text-[10px] text-ink-soft tabular-nums">({{ $make->published_count }})</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Search by Type --}}
                    <div class="bg-white border border-line rounded-sm overflow-hidden">
                        @include('partials.home-sidebar-header', ['kicker' => 'Search by', 'title' => 'Type'])
                        <ul class="text-[13px]">
                            @foreach ($bodyTypes as $bt)
                                @php($selected = ($filters['body_type'] ?? '') === $bt->slug)
                                <li>
                                    <a href="{{ route('vehicles.index').'?'.$qs(['body_type' => $selected ? null : $bt->slug]) }}"
                                       class="flex items-center gap-2 px-3 py-2 border-b border-line/60 last:border-b-0 transition {{ $selected ? 'bg-toco-silver-2 text-toco-red' : 'hover:bg-toco-silver-2' }}">
                                        <span class="w-7 h-7 grid place-items-center text-toco-navy shrink-0">
                                            @if ($bt->getLogoUrl())
                                                <img src="{{ $bt->getLogoUrl() }}" alt="" class="max-w-full max-h-full object-contain" loading="lazy">
                                            @else
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="9" width="18" height="8" rx="2"/><circle cx="7.5" cy="18" r="1.5"/><circle cx="16.5" cy="18" r="1.5"/></svg>
                                            @endif
                                        </span>
                                        <span class="flex-1 font-semibold">{{ $bt->name }}</span>
                                        @if ($showStockCounts && ($bt->published_count ?? 0))
                                            <span class="font-mono text-[10px] text-ink-soft tabular-nums">({{ $bt->published_count }})</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </aside>

                {{-- ============ MAIN COLUMN ============ --}}
                <div class="flex flex-col gap-4 min-w-0">

                    {{-- TOP FILTER BAR --}}
                    <form method="GET" action="{{ route('vehicles.index') }}" class="bg-white border border-line rounded-sm" x-data="{ advanced: false }">
                        {{-- Bar header --}}
                        <div class="flex items-center justify-between border-b-2 border-toco-red/30 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10143A" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                <h1 class="font-extrabold text-toco-navy text-base md:text-lg tracking-tight">Search for Used Cars</h1>
                            </div>
                            <p class="text-[12px] text-ink-soft hidden sm:block">{{ number_format($vehicles->total()) }} hit</p>
                        </div>

                        {{-- Row 1 --}}
                        <div class="p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Make</label>
                                <select name="make" class="w-full text-sm">
                                    <option value="">Any Make</option>
                                    @foreach ($makes as $m)
                                        <option value="{{ $m->slug }}" @selected(($filters['make'] ?? '') === $m->slug) {{ ($m->published_count ?? 0) === 0 ? 'disabled' : '' }}>{{ $m->name }}@if ($showStockCounts) ({{ $m->published_count ?? 0 }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Model</label>
                                <select name="vehicle_model" class="w-full text-sm" {{ $models->isEmpty() ? 'disabled' : '' }}>
                                    <option value="">Any Model</option>
                                    @foreach ($models as $m)
                                        <option value="{{ $m->slug }}" @selected(($filters['vehicle_model'] ?? '') === $m->slug)>{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Type</label>
                                <select name="body_type" class="w-full text-sm">
                                    <option value="">Any Type</option>
                                    @foreach ($bodyTypes as $b)
                                        <option value="{{ $b->slug }}" @selected(($filters['body_type'] ?? '') === $b->slug) {{ ($b->published_count ?? 0) === 0 ? 'disabled' : '' }}>{{ $b->name }}@if ($showStockCounts) ({{ $b->published_count ?? 0 }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Registration Year</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <input type="number" name="year_from" min="1980" max="{{ (int) date('Y') + 1 }}" placeholder="FROM" value="{{ $filters['year_from'] ?? '' }}" class="text-sm">
                                    <input type="number" name="year_to" min="1980" max="{{ (int) date('Y') + 1 }}" placeholder="TO" value="{{ $filters['year_to'] ?? '' }}" class="text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Price Range (USD)</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <input type="number" name="price_from" min="0" placeholder="MIN" value="{{ $filters['price_from'] ?? '' }}" class="text-sm">
                                    <input type="number" name="price_to" min="0" placeholder="MAX" value="{{ $filters['price_to'] ?? '' }}" class="text-sm">
                                </div>
                            </div>
                        </div>

                        {{-- Row 2 (advanced) --}}
                        <div x-show="advanced" x-cloak class="px-4 pb-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Steering</label>
                                <div class="grid grid-cols-3 gap-0 text-[11px] font-bold">
                                    @foreach (['' => 'Any', 'left' => 'Left', 'right' => 'Right'] as $val => $label)
                                        @php($checked = ($filters['steering'] ?? '') === $val)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="steering" value="{{ $val }}" {{ $checked ? 'checked' : '' }} class="peer sr-only">
                                            <span class="block text-center py-1.5 border border-line peer-checked:bg-toco-navy peer-checked:text-white peer-checked:border-toco-navy transition">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Transmission</label>
                                <div class="grid grid-cols-3 gap-0 text-[11px] font-bold">
                                    @foreach (['' => 'Any', 'automatic' => 'AT', 'manual' => 'MT'] as $val => $label)
                                        @php($checked = ($filters['transmission'] ?? '') === $val)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="transmission" value="{{ $val }}" {{ $checked ? 'checked' : '' }} class="peer sr-only">
                                            <span class="block text-center py-1.5 border border-line peer-checked:bg-toco-navy peer-checked:text-white peer-checked:border-toco-navy transition">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Mileage (km)</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <input type="number" name="mileage_min" min="0" placeholder="MIN" value="{{ $filters['mileage_min'] ?? '' }}" class="text-sm">
                                    <input type="number" name="mileage_max" min="0" placeholder="MAX" value="{{ $filters['mileage_max'] ?? '' }}" class="text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block font-mono text-[9px] uppercase tracking-widest text-ink-soft mb-1">Engine Size (cc)</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <input type="number" name="engine_from" min="0" placeholder="MIN" value="{{ $filters['engine_from'] ?? '' }}" class="text-sm">
                                    <input type="number" name="engine_to" min="0" placeholder="MAX" value="{{ $filters['engine_to'] ?? '' }}" class="text-sm">
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5 self-end pb-0.5">
                                <label class="flex items-center gap-2 text-[12px] font-semibold text-ink cursor-pointer">
                                    <input type="checkbox" name="discounted" value="1" {{ ! empty($filters['discounted']) ? 'checked' : '' }} class="text-toco-red">
                                    Discounted Cars
                                </label>
                                <label class="flex items-center gap-2 text-[12px] font-semibold text-ink cursor-pointer">
                                    <input type="checkbox" name="new_only" value="1" {{ ! empty($filters['new_only']) ? 'checked' : '' }} class="text-toco-red">
                                    New Arrivals
                                </label>
                            </div>
                        </div>

                        {{-- Active filter chips --}}
                        @php($activeChips = array_filter($chipDefs))
                        @if (! empty($activeChips))
                            <div class="px-4 pb-2 flex flex-wrap gap-1.5">
                                @foreach ($activeChips as $key => $label)
                                    <a href="{{ route('vehicles.index').'?'.$qs([$key => null]) }}"
                                       class="inline-flex items-center gap-1.5 bg-toco-silver-2 border border-line text-ink text-[12px] font-semibold px-2.5 py-1 rounded-sm hover:border-toco-red hover:text-toco-red transition">
                                        <span>{{ $label }}</span>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M6 18 18 6"/></svg>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        {{-- Bar footer: Search · advanced · reset --}}
                        <div class="border-t border-line px-4 py-3 grid grid-cols-1 sm:grid-cols-[auto_1fr_auto] gap-3 items-center">
                            <button type="button" @click="advanced = !advanced" class="text-[11px] font-mono uppercase tracking-widest font-bold text-toco-navy hover:text-toco-red inline-flex items-center gap-1.5 self-center">
                                <span x-text="advanced ? 'Hide advanced' : 'Advanced search'"></span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" :class="advanced ? 'rotate-180' : ''" class="transition-transform"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <button type="submit" class="bg-toco-navy hover:bg-toco-navy-deep text-white font-bold uppercase tracking-widest text-xs px-6 py-2.5 rounded-sm inline-flex items-center justify-center gap-2 justify-self-center">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                Search · {{ number_format($vehicles->total()) }} hit
                            </button>
                            <a href="{{ route('vehicles.index') }}" class="text-center border border-line hover:border-toco-red hover:text-toco-red text-ink font-bold uppercase tracking-widest text-[11px] px-5 py-2.5 rounded-sm self-center justify-self-end">Reset</a>
                        </div>
                    </form>

                    {{-- TOTAL PRICE / CIF calculator strip --}}
                    @php($currentDestPort = $destPort ?? null)
                    <form method="POST" action="{{ route('destination.set') }}" class="bg-white border border-line rounded-sm px-4 py-3 grid grid-cols-1 md:grid-cols-[auto_1fr_auto_auto] gap-3 items-center"
                          x-data="{
                              countries: {{ $destPayload->toJson() }},
                              countryId: '{{ $currentDestPort?->country_id }}',
                              portId: '{{ $currentDestPort?->id }}',
                              ports() { return (this.countries.find(c => c.id == this.countryId) || { ports: [] }).ports },
                          }">
                        @csrf
                        <p class="font-extrabold text-toco-navy text-sm whitespace-nowrap">Total Price Calculator</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <select x-model="countryId" name="country_id" required class="text-sm">
                                <option value="">Country</option>
                                <template x-for="c in countries" :key="c.id">
                                    <option :value="c.id" x-text="c.name"></option>
                                </template>
                            </select>
                            <select x-model="portId" name="port_id" required :disabled="!countryId" class="text-sm disabled:bg-toco-silver-2">
                                <option value="">Port</option>
                                <template x-for="p in ports()" :key="p.id">
                                    <option :value="p.id" x-text="p.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="hidden md:flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-ink-soft">
                            <span>CIF prices show in card list once selected.</span>
                        </div>
                        <button type="submit" class="bg-toco-navy hover:bg-toco-navy-deep text-white font-bold uppercase tracking-widest text-xs px-5 py-2.5 rounded-sm inline-flex items-center gap-2 whitespace-nowrap">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
                            Calculate
                        </button>
                    </form>

                    {{-- RESULTS HEADER --}}
                    @php($perPage = (int) ($filters['per_page'] ?? 20))
                    <div class="bg-white border border-line rounded-sm px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <div class="flex items-center gap-4 flex-wrap">
                            <p class="text-ink"><span class="text-ink-soft">Result:</span> <strong>{{ number_format($vehicles->total()) }}</strong></p>
                            <p class="text-ink-soft inline-flex items-center gap-1.5">
                                No. of Display:
                                @foreach ([20, 50, 100] as $size)
                                    <a href="{{ route('vehicles.index').'?'.$qs(['per_page' => $size, 'page' => null]) }}"
                                       class="font-bold {{ $perPage === $size ? 'text-toco-red underline' : 'text-toco-navy hover:text-toco-red' }}">{{ $size }}</a>
                                @endforeach
                            </p>
                        </div>
                        <form method="GET" class="inline-flex items-center gap-2">
                            @foreach ($filters as $k => $v)
                                @if ($k !== 'sort' && $k !== 'page' && $v !== null && $v !== '')
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
                            <span class="text-ink-soft text-[12px]">Sort by</span>
                            <select name="sort" onchange="this.form.submit()" class="text-sm py-1">
                                <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Latest first</option>
                                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price (low to high)</option>
                                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price (high to low)</option>
                                <option value="year_desc" @selected(($filters['sort'] ?? '') === 'year_desc')>Year (new to old)</option>
                                <option value="year_asc" @selected(($filters['sort'] ?? '') === 'year_asc')>Year (old to new)</option>
                            </select>
                        </form>
                    </div>

                    {{-- VEHICLE ROW LIST --}}
                    @if ($vehicles->isEmpty())
                        <div class="bg-white border border-line rounded-sm p-12 text-center text-ink-soft">
                            No vehicles match these filters. <a href="{{ route('vehicles.index') }}" class="text-toco-red font-semibold">Reset filters</a>
                        </div>
                    @else
                        <div class="flex flex-col gap-3">
                            @foreach ($vehicles as $vehicle)
                                <x-vehicle-row :vehicle="$vehicle" />
                            @endforeach
                        </div>

                        <div class="mt-2">{{ $vehicles->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
