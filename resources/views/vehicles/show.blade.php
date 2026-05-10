@php
    $title = $vehicle->title.' — Toco Japan';
@endphp

<x-layouts.site :title="$title">
    <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
        <div class="space-y-4">
            <div class="bg-white border border-line rounded-md overflow-hidden">
                @if ($photo = $vehicle->getFirstMediaUrl('photos'))
                    <img src="{{ $photo }}" alt="{{ $vehicle->title }}" class="w-full aspect-video object-cover">
                @else
                    <div class="aspect-video bg-surface-2 flex items-center justify-center text-ink-soft">No photo available</div>
                @endif
            </div>

            @if ($vehicle->getMedia('photos')->count() > 1)
                <div class="grid grid-cols-4 gap-2">
                    @foreach ($vehicle->getMedia('photos') as $media)
                        <a href="{{ $media->getUrl() }}" target="_blank" class="block bg-surface-2 rounded overflow-hidden">
                            <img src="{{ $media->getUrl() }}" alt="" class="w-full aspect-video object-cover">
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($vehicle->description)
                <div class="bg-white border border-line rounded-md p-4">
                    <h2 class="font-bold text-toco-navy mb-2">Description</h2>
                    <div class="prose prose-sm max-w-none">{!! nl2br(e($vehicle->description)) !!}</div>
                </div>
            @endif

            @if (! empty($vehicle->features))
                <div class="bg-white border border-line rounded-md p-4">
                    <h2 class="font-bold text-toco-navy mb-2">Features</h2>
                    @foreach ($vehicle->features as $group => $items)
                        @if (is_array($items))
                            <p class="font-semibold text-sm capitalize mt-3 mb-1">{{ str_replace('_', ' ', (string) $group) }}</p>
                            <ul class="grid grid-cols-2 gap-x-4 text-sm list-disc list-inside text-ink-soft">
                                @foreach ($items as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="bg-white border border-line border-t-4 border-t-toco-red rounded-md p-4">
                <p class="text-xs uppercase tracking-widest text-ink-soft">{{ $vehicle->ref_no }}</p>
                <h1 class="text-xl font-extrabold text-toco-navy">{{ $vehicle->title }}</h1>
                <p class="font-bold text-2xl text-toco-red mt-3">
                    @if ($vehicle->price_on_request)
                        Price on request
                    @else
                        ${{ number_format((float) $vehicle->price_fob) }}
                        <span class="text-xs text-ink-soft">{{ $vehicle->currency }} FOB</span>
                    @endif
                </p>
                <a href="{{ route('register') }}" class="block text-center mt-4 bg-toco-red hover:bg-toco-red-deep text-white font-semibold uppercase tracking-widest text-xs px-4 py-2 rounded">Request a quote</a>
            </div>

            <div class="bg-white border border-line rounded-md p-4 text-sm">
                <h2 class="font-bold text-toco-navy mb-2">Specifications</h2>
                <dl class="grid grid-cols-2 gap-y-1">
                    <dt class="text-ink-soft">Make</dt><dd>{{ $vehicle->make->name ?? '—' }}</dd>
                    <dt class="text-ink-soft">Model</dt><dd>{{ $vehicle->vehicleModel->name ?? '—' }}</dd>
                    <dt class="text-ink-soft">Body type</dt><dd>{{ $vehicle->bodyType->name ?? '—' }}</dd>
                    <dt class="text-ink-soft">Year</dt><dd>{{ $vehicle->year_first_reg }}</dd>
                    <dt class="text-ink-soft">Mileage</dt><dd>{{ number_format($vehicle->mileage_km) }} km</dd>
                    <dt class="text-ink-soft">Engine</dt><dd>{{ $vehicle->engine_cc }} cc</dd>
                    <dt class="text-ink-soft">Fuel</dt><dd>{{ ucfirst((string) $vehicle->fuel) }}</dd>
                    <dt class="text-ink-soft">Transmission</dt><dd>{{ ucfirst((string) $vehicle->transmission) }}</dd>
                    <dt class="text-ink-soft">Drive</dt><dd>{{ strtoupper((string) $vehicle->drive) }}</dd>
                    <dt class="text-ink-soft">Steering</dt><dd>{{ $vehicle->steering_side === 'right' ? 'RHD' : 'LHD' }}</dd>
                    <dt class="text-ink-soft">Doors / Seats</dt><dd>{{ $vehicle->doors }} / {{ $vehicle->seats }}</dd>
                    <dt class="text-ink-soft">Exterior</dt><dd>{{ $vehicle->exterior_color }}</dd>
                    <dt class="text-ink-soft">Interior</dt><dd>{{ $vehicle->interior_color }}</dd>
                    <dt class="text-ink-soft">Dimensions</dt><dd>{{ $vehicle->length_cm }}×{{ $vehicle->width_cm }}×{{ $vehicle->height_cm }} cm</dd>
                    <dt class="text-ink-soft">M³ (shipping)</dt><dd>{{ $vehicle->m3 }}</dd>
                    <dt class="text-ink-soft">Warranty</dt><dd>{{ $vehicle->warranty_period ?? '—' }}</dd>
                </dl>
            </div>
        </aside>
    </div>
</x-layouts.site>
