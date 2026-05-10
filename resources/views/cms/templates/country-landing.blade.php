<x-layouts.cms :page="$page">
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-12 md:py-16">
            @if (! empty($page->data['kicker']))
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $page->data['kicker'] }}</p>
            @elseif ($country)
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">Destination · {{ $country->iso2 }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight max-w-3xl">
                {{ $page->data['headline'] ?? $page->title }}
            </h1>
            @if (! empty($page->data['intro']))
                <div class="mt-4 text-white/80 text-base max-w-2xl prose prose-invert">{!! $page->data['intro'] !!}</div>
            @endif
        </div>
    </section>

    <section class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-10">
        @if ($country && $country->ports->isNotEmpty())
            <div class="bg-white border border-line rounded-sm overflow-hidden mb-8">
                <div class="px-5 py-3 border-b border-line">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Destination ports</p>
                    <h2 class="font-bold text-toco-navy">Shipping to {{ $country->name }}</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-toco-silver-2 text-ink-soft">
                        <tr>
                            <th class="text-left font-mono text-[10px] uppercase tracking-widest px-4 py-2">Port</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-widest px-4 py-2">UNLOCODE</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-widest px-4 py-2">Rate (USD/m³)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($country->ports as $port)
                            <tr>
                                <td class="px-4 py-2 font-semibold text-toco-navy">{{ $port->name }}</td>
                                <td class="px-4 py-2 font-mono text-[12px] text-ink-soft">{{ $port->unlocode ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums">${{ number_format((float) $port->rate_per_m3, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-5 py-3 border-t border-line bg-toco-silver-2 text-[12px] text-ink-soft">
                    Use the <a href="{{ route('cif.index') }}" class="text-toco-red font-semibold">CIF calculator</a> to estimate landed cost from FOB Yokohama to any of these ports.
                </div>
            </div>
        @endif

        @if (! empty($page->data['body']))
            <article class="prose max-w-none mb-10">{!! $page->data['body'] !!}</article>
        @endif

        @if ($popularVehicles->isNotEmpty())
            <div class="mb-3">
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">Popular stock</p>
                <h2 class="text-2xl font-extrabold text-toco-navy mt-1">Vehicles ready to ship</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($popularVehicles as $vehicle)
                    <x-vehicle-card :vehicle="$vehicle" />
                @endforeach
            </div>
            <div class="mt-6">
                <a href="{{ route('vehicles.index') }}" class="text-sm font-bold text-toco-red hover:underline">Browse all stock →</a>
            </div>
        @endif
    </section>
</x-layouts.cms>
