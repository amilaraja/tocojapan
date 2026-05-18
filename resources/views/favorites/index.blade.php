@php
    $title = 'My wishlist — Toco Japan';
@endphp

<x-layouts.account :title="$title" heading="My Wishlist" active="favorites">
    @if (session('flash'))
        <div class="bg-toco-silver-2 border border-toco-navy/20 text-toco-navy px-4 py-2 rounded-sm text-sm mb-4 font-mono">{{ session('flash') }}</div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-ink-soft">{{ $vehicles->total() }} vehicle{{ $vehicles->total() === 1 ? '' : 's' }} saved</p>
    </div>

    @if ($vehicles->isEmpty())
        <div class="bg-white border border-line rounded-sm p-12 text-center text-ink-soft">
            You haven't saved any vehicles yet. <a href="{{ route('vehicles.index') }}" class="text-toco-red font-semibold">Browse stock</a> and tap the heart icon to save vehicles you like.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
            @foreach ($vehicles as $vehicle)
                <x-vehicle-card :vehicle="$vehicle" />
            @endforeach
        </div>
        <div class="mt-6">{{ $vehicles->links() }}</div>
    @endif
</x-layouts.account>
