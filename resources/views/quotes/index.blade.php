@php
    $title = 'My quotes — Toco Japan';
@endphp

<x-layouts.site :title="$title">
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="w-full px-6 2xl:px-8 py-8 md:py-10">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">My Toco</p>
            <h1 class="text-2xl md:text-3xl font-extrabold mt-1">My quote requests</h1>
            <p class="text-white/70 text-sm mt-1">{{ $quotes->total() }} {{ Str::plural('quote', $quotes->total()) }}</p>
        </div>
    </section>

    <section class="w-full px-6 2xl:px-8 py-8">
        @if (session('flash'))
            <div class="bg-toco-silver-2 border border-toco-navy/20 text-toco-navy px-4 py-2 rounded-sm text-sm mb-4 font-mono">{{ session('flash') }}</div>
        @endif

        @if ($quotes->isEmpty())
            <div class="bg-white border border-line rounded-sm p-12 text-center text-ink-soft">
                You haven't requested any quotes yet. <a href="{{ route('vehicles.index') }}" class="text-toco-red font-semibold">Browse vehicles</a> to start one.
            </div>
        @else
            <div class="bg-white border border-line rounded-sm divide-y divide-line">
                @foreach ($quotes as $quote)
                    <a href="{{ route('quotes.show', $quote) }}" class="block px-5 py-4 hover:bg-toco-silver-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">{{ $quote->reference }} · {{ $quote->created_at->diffForHumans() }}</p>
                                <p class="font-bold text-toco-navy mt-0.5 truncate">{{ $quote->vehicle?->title ?? 'General enquiry' }}</p>
                                <p class="text-[12px] text-ink-soft mt-0.5">
                                    @if ($quote->port)
                                        Destination: {{ $quote->port->name }}, {{ $quote->country?->name }}
                                    @else
                                        No destination set
                                    @endif
                                </p>
                            </div>
                            <div class="text-right">
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
                                @if ($quote->price_quoted)
                                    <p class="font-bold text-toco-red mt-1">${{ number_format((float) $quote->price_quoted) }}</p>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $quotes->links() }}</div>
        @endif
    </section>
</x-layouts.site>
