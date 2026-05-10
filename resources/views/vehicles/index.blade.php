@php
    $title = 'Vehicles for export — Toco Japan';
@endphp

<x-layouts.site :title="$title">
    <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">
        <aside class="bg-white border border-line rounded-md p-4">
            <h2 class="font-bold text-toco-navy mb-3 text-sm uppercase tracking-wide">Filter vehicles</h2>
            <form method="GET" action="{{ route('vehicles.index') }}" class="space-y-3 text-sm">
                <div>
                    <label class="block font-medium mb-1">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ref no, title…" class="w-full border-line rounded">
                </div>
                <div>
                    <label class="block font-medium mb-1">Make</label>
                    <select name="make" class="w-full border-line rounded">
                        <option value="">— Any —</option>
                        @foreach ($makes as $m)
                            <option value="{{ $m->slug }}" @selected(($filters['make'] ?? '') === $m->slug)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($models->isNotEmpty())
                    <div>
                        <label class="block font-medium mb-1">Model</label>
                        <select name="vehicle_model" class="w-full border-line rounded">
                            <option value="">— Any —</option>
                            @foreach ($models as $m)
                                <option value="{{ $m->slug }}" @selected(($filters['vehicle_model'] ?? '') === $m->slug)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block font-medium mb-1">Body type</label>
                    <select name="body_type" class="w-full border-line rounded">
                        <option value="">— Any —</option>
                        @foreach ($bodyTypes as $b)
                            <option value="{{ $b->slug }}" @selected(($filters['body_type'] ?? '') === $b->slug)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-medium mb-1">Year from</label>
                        <input type="number" name="year_from" min="1980" max="2030" value="{{ $filters['year_from'] ?? '' }}" class="w-full border-line rounded">
                    </div>
                    <div>
                        <label class="block font-medium mb-1">Year to</label>
                        <input type="number" name="year_to" min="1980" max="2030" value="{{ $filters['year_to'] ?? '' }}" class="w-full border-line rounded">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-medium mb-1">Price from</label>
                        <input type="number" name="price_from" min="0" value="{{ $filters['price_from'] ?? '' }}" class="w-full border-line rounded">
                    </div>
                    <div>
                        <label class="block font-medium mb-1">Price to</label>
                        <input type="number" name="price_to" min="0" value="{{ $filters['price_to'] ?? '' }}" class="w-full border-line rounded">
                    </div>
                </div>
                <div>
                    <label class="block font-medium mb-1">Mileage max (km)</label>
                    <input type="number" name="mileage_max" min="0" value="{{ $filters['mileage_max'] ?? '' }}" class="w-full border-line rounded">
                </div>
                <div>
                    <label class="block font-medium mb-1">Transmission</label>
                    <select name="transmission" class="w-full border-line rounded">
                        <option value="">— Any —</option>
                        @foreach (['automatic' => 'Automatic', 'manual' => 'Manual', 'cvt' => 'CVT'] as $k => $v)
                            <option value="{{ $k }}" @selected(($filters['transmission'] ?? '') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-medium mb-1">Fuel</label>
                    <select name="fuel" class="w-full border-line rounded">
                        <option value="">— Any —</option>
                        @foreach (['petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric', 'lpg' => 'LPG'] as $k => $v)
                            <option value="{{ $k }}" @selected(($filters['fuel'] ?? '') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-medium mb-1">Steering</label>
                    <select name="steering" class="w-full border-line rounded">
                        <option value="">— Any —</option>
                        <option value="right" @selected(($filters['steering'] ?? '') === 'right')>Right (RHD)</option>
                        <option value="left" @selected(($filters['steering'] ?? '') === 'left')>Left (LHD)</option>
                    </select>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 bg-toco-red hover:bg-toco-red-deep text-white font-semibold uppercase tracking-widest text-xs px-4 py-2 rounded">Apply</button>
                    <a href="{{ route('vehicles.index') }}" class="flex-1 text-center border border-line hover:bg-surface-2 text-ink-soft font-semibold uppercase tracking-widest text-xs px-4 py-2 rounded">Reset</a>
                </div>
            </form>
        </aside>

        <section>
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-ink-soft">{{ $vehicles->total() }} vehicle(s) found</p>
                <form method="GET" class="text-sm">
                    @foreach ($filters as $k => $v)
                        @if ($k !== 'sort' && $v !== null && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <label>Sort:
                        <select name="sort" onchange="this.form.submit()" class="border-line rounded">
                            <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Latest</option>
                            <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price ↑</option>
                            <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price ↓</option>
                            <option value="year_desc" @selected(($filters['sort'] ?? '') === 'year_desc')>Year ↓</option>
                            <option value="year_asc" @selected(($filters['sort'] ?? '') === 'year_asc')>Year ↑</option>
                        </select>
                    </label>
                </form>
            </div>

            @if ($vehicles->isEmpty())
                <div class="bg-white border border-line rounded p-8 text-center text-ink-soft">No vehicles match these filters.</div>
            @else
                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($vehicles as $vehicle)
                        <a href="{{ route('vehicles.show', $vehicle->slug) }}" class="bg-white border border-line rounded-md overflow-hidden hover:border-toco-red transition">
                            <div class="aspect-video bg-surface-2 flex items-center justify-center text-ink-soft text-xs">
                                @if ($photo = $vehicle->getFirstMediaUrl('photos'))
                                    <img src="{{ $photo }}" alt="{{ $vehicle->title }}" class="w-full h-full object-cover">
                                @else
                                    No photo
                                @endif
                            </div>
                            <div class="p-3">
                                <p class="text-xs uppercase text-ink-soft">{{ $vehicle->ref_no }}</p>
                                <h3 class="font-semibold text-toco-navy line-clamp-1">{{ $vehicle->title }}</h3>
                                <p class="text-xs text-ink-soft mt-1">{{ number_format($vehicle->mileage_km) }} km · {{ $vehicle->engine_cc }}cc · {{ ucfirst((string) $vehicle->transmission) }}</p>
                                <p class="font-bold text-toco-red mt-2">
                                    @if ($vehicle->price_on_request)
                                        Price on request
                                    @else
                                        ${{ number_format((float) $vehicle->price_fob) }} <span class="text-xs text-ink-soft">{{ $vehicle->currency }} FOB</span>
                                    @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">{{ $vehicles->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.site>
