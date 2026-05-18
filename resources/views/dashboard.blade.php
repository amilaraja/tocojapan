@php
    $title = 'My Toco — Dashboard';
@endphp

<x-layouts.account :title="$title" heading="Welcome back, {{ Auth::user()->name }}." active="dashboard">
    @if (session('flash'))
        <div class="bg-toco-silver-2 border border-toco-navy/20 text-toco-navy px-4 py-2 rounded-sm text-sm mb-4 font-mono">{{ session('flash') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <a href="{{ route('favorites.index') }}" class="bg-white border border-line hover:border-toco-red rounded-sm p-5 transition">
            <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Wishlist</p>
            <p class="text-3xl font-extrabold text-toco-navy mt-1">{{ $favoritesCount }}</p>
            <p class="text-xs text-toco-red font-bold mt-3">View wishlist →</p>
        </a>
        <a href="{{ route('quotes.index') }}" class="bg-white border border-line hover:border-toco-red rounded-sm p-5 transition">
            <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Open quotes</p>
            <p class="text-3xl font-extrabold text-toco-navy mt-1">{{ $openQuotesCount }}</p>
            <p class="text-xs text-toco-red font-bold mt-3">View quotes →</p>
        </a>
        <a href="{{ route('orders.index') }}" class="bg-white border border-line hover:border-toco-red rounded-sm p-5 transition">
            <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">My orders</p>
            <p class="text-3xl font-extrabold text-toco-navy mt-1">{{ Auth::user()->orders()->count() }}</p>
            <p class="text-xs text-toco-red font-bold mt-3">View orders →</p>
        </a>
        <a href="{{ route('vehicles.index') }}" class="bg-toco-navy text-white border border-toco-navy hover:bg-toco-navy-deep rounded-sm p-5 transition">
            <p class="font-mono text-[10px] uppercase tracking-widest text-white/70">Browse</p>
            <p class="text-2xl font-extrabold mt-1 leading-tight">Find your next vehicle</p>
            <p class="text-xs text-toco-red font-bold mt-3">Open stock →</p>
        </a>
    </div>

    <div class="bg-white border border-line rounded-sm">
        <div class="px-5 py-3 border-b border-line">
            <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Recent activity</p>
            <h2 class="font-bold text-toco-navy">Your latest quote requests</h2>
        </div>
        @if ($recentQuotes->isEmpty())
            <div class="p-8 text-center text-ink-soft text-sm">
                No quote requests yet. <a href="{{ route('vehicles.index') }}" class="text-toco-red font-semibold">Browse vehicles</a> to start one.
            </div>
        @else
            <ul class="divide-y divide-line text-sm">
                @foreach ($recentQuotes as $quote)
                    <li>
                        <a href="{{ route('quotes.show', $quote) }}" class="flex items-center justify-between px-5 py-3 hover:bg-toco-silver-2">
                            <div>
                                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">{{ $quote->reference }} · {{ $quote->created_at->diffForHumans() }}</p>
                                <p class="font-bold text-toco-navy">{{ $quote->vehicle?->title ?? 'General enquiry' }}</p>
                            </div>
                            <span class="font-mono text-[10px] uppercase tracking-widest px-2 py-1 rounded-sm
                                @class([
                                    'bg-toco-silver text-toco-navy' => in_array($quote->status, ['submitted', 'archived']),
                                    'bg-yellow-100 text-yellow-900' => $quote->status === 'in_progress',
                                    'bg-green-100 text-green-900' => $quote->status === 'quoted',
                                    'bg-toco-red text-white' => $quote->status === 'accepted',
                                    'bg-red-100 text-red-900' => $quote->status === 'declined',
                                ])">
                                {{ \App\Models\Quote::STATUSES[$quote->status] ?? $quote->status }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.account>
