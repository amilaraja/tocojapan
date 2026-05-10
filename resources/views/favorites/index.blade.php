@php
    $title = 'Saved vehicles — Toco Japan';
@endphp

<x-layouts.site :title="$title">
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1440px] mx-auto px-6 py-8 md:py-10">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">My Toco</p>
            <h1 class="text-2xl md:text-3xl font-extrabold mt-1">Saved vehicles</h1>
            <p class="text-white/70 text-sm mt-1">{{ $vehicles->total() }} saved</p>
        </div>
    </section>

    <section class="max-w-[1440px] mx-auto px-6 py-8">
        @if (session('flash'))
            <div class="bg-toco-silver-2 border border-toco-navy/20 text-toco-navy px-4 py-2 rounded-sm text-sm mb-4 font-mono">{{ session('flash') }}</div>
        @endif

        @if ($vehicles->isEmpty())
            <div class="bg-white border border-line rounded-sm p-12 text-center text-ink-soft">
                You haven't saved any vehicles yet. <a href="{{ route('vehicles.index') }}" class="text-toco-red font-semibold">Browse stock</a> and tap the heart icon to save vehicles you like.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach ($vehicles as $vehicle)
                    <x-vehicle-card :vehicle="$vehicle" />
                @endforeach
            </div>
            <div class="mt-6">{{ $vehicles->links() }}</div>
        @endif
    </section>
</x-layouts.site>
