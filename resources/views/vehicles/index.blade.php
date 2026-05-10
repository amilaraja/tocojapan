@php
    $title = 'Vehicles for export — Toco Japan';
@endphp

<x-layouts.site :title="$title">
    {{-- Page header band --}}
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1440px] mx-auto px-6 py-8 md:py-10">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">Stock</p>
            <h1 class="text-2xl md:text-3xl font-extrabold mt-1">Vehicles for export</h1>
            <p class="text-white/70 text-sm mt-1">{{ number_format($vehicles->total()) }} {{ Str::plural('vehicle', $vehicles->total()) }} matching your filters</p>
        </div>
    </section>

    <section class="max-w-[1440px] mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-6">
            {{-- Filter sidebar --}}
            <aside class="bg-white border border-line rounded-sm h-fit sticky top-20 self-start">
                <div class="px-4 py-3 border-b border-line">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Refine</p>
                    <h2 class="font-bold text-toco-navy">Filter vehicles</h2>
                </div>
                <form method="GET" action="{{ route('vehicles.index') }}" class="p-4 space-y-3 text-sm">
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Search</label>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ref no, title…" class="w-full border-line rounded-sm">
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Make</label>
                        <select name="make" class="w-full border-line rounded-sm">
                            <option value="">Any make</option>
                            @foreach ($makes as $m)
                                <option value="{{ $m->slug }}" @selected(($filters['make'] ?? '') === $m->slug)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($models->isNotEmpty())
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Model</label>
                            <select name="vehicle_model" class="w-full border-line rounded-sm">
                                <option value="">Any model</option>
                                @foreach ($models as $m)
                                    <option value="{{ $m->slug }}" @selected(($filters['vehicle_model'] ?? '') === $m->slug)>{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Body type</label>
                        <select name="body_type" class="w-full border-line rounded-sm">
                            <option value="">Any</option>
                            @foreach ($bodyTypes as $b)
                                <option value="{{ $b->slug }}" @selected(($filters['body_type'] ?? '') === $b->slug)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Year from</label>
                            <input type="number" name="year_from" min="1980" max="2030" value="{{ $filters['year_from'] ?? '' }}" class="w-full border-line rounded-sm">
                        </div>
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Year to</label>
                            <input type="number" name="year_to" min="1980" max="2030" value="{{ $filters['year_to'] ?? '' }}" class="w-full border-line rounded-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Price from</label>
                            <input type="number" name="price_from" min="0" value="{{ $filters['price_from'] ?? '' }}" class="w-full border-line rounded-sm">
                        </div>
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Price to</label>
                            <input type="number" name="price_to" min="0" value="{{ $filters['price_to'] ?? '' }}" class="w-full border-line rounded-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Mileage max (km)</label>
                        <input type="number" name="mileage_max" min="0" value="{{ $filters['mileage_max'] ?? '' }}" class="w-full border-line rounded-sm">
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Transmission</label>
                        <select name="transmission" class="w-full border-line rounded-sm">
                            <option value="">Any</option>
                            @foreach (['automatic' => 'Automatic', 'manual' => 'Manual', 'cvt' => 'CVT'] as $k => $v)
                                <option value="{{ $k }}" @selected(($filters['transmission'] ?? '') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Fuel</label>
                        <select name="fuel" class="w-full border-line rounded-sm">
                            <option value="">Any</option>
                            @foreach (['petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric', 'lpg' => 'LPG'] as $k => $v)
                                <option value="{{ $k }}" @selected(($filters['fuel'] ?? '') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Steering</label>
                        <select name="steering" class="w-full border-line rounded-sm">
                            <option value="">Any</option>
                            <option value="right" @selected(($filters['steering'] ?? '') === 'right')>Right (RHD)</option>
                            <option value="left" @selected(($filters['steering'] ?? '') === 'left')>Left (LHD)</option>
                        </select>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="flex-1 bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-[11px] px-4 py-2.5 rounded-sm">Apply</button>
                        <a href="{{ route('vehicles.index') }}" class="flex-1 text-center border border-line hover:bg-toco-silver-2 text-ink font-bold uppercase tracking-widest text-[11px] px-4 py-2.5 rounded-sm">Reset</a>
                    </div>
                </form>
            </aside>

            <div>
                <div class="bg-white border border-line rounded-sm px-4 py-3 mb-4 flex items-center justify-between">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">{{ $vehicles->total() }} results</p>
                    <form method="GET" class="text-sm flex items-center gap-2">
                        @foreach ($filters as $k => $v)
                            @if ($k !== 'sort' && $v !== null && $v !== '')
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <span class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Sort</span>
                        <select name="sort" onchange="this.form.submit()" class="border-line rounded-sm text-sm py-1">
                            <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Latest</option>
                            <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price ↑</option>
                            <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price ↓</option>
                            <option value="year_desc" @selected(($filters['sort'] ?? '') === 'year_desc')>Year ↓</option>
                            <option value="year_asc" @selected(($filters['sort'] ?? '') === 'year_asc')>Year ↑</option>
                        </select>
                    </form>
                </div>

                @if ($vehicles->isEmpty())
                    <div class="bg-white border border-line rounded-sm p-12 text-center text-ink-soft">No vehicles match these filters. <a href="{{ route('vehicles.index') }}" class="text-toco-red font-semibold">Reset filters</a></div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach ($vehicles as $vehicle)
                            <x-vehicle-card :vehicle="$vehicle" />
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $vehicles->links() }}</div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.site>
