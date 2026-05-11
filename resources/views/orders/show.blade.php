<x-layouts.site :title="$order->order_no.' — Toco Japan'">
    <section class="max-w-[1100px] mx-auto px-6 py-10 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('orders.index') }}" class="text-xs uppercase tracking-widest text-ink-soft hover:text-toco-red">← All orders</a>
                <h1 class="text-2xl font-extrabold text-toco-navy mt-1">{{ $order->order_no }}</h1>
            </div>
            <span class="inline-block text-[11px] font-bold uppercase tracking-widest px-3 py-1.5 rounded {{ $order->isPaid() ? 'bg-green-100 text-green-800' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                {{ $order->statusLabel() }}
            </span>
        </div>

        <div class="bg-white border border-line rounded-sm p-5 flex items-center gap-4">
            @php($photo = $order->vehicle->getFirstMediaUrl('photos'))
            <div class="w-28 h-20 bg-toco-silver-2 rounded-sm overflow-hidden shrink-0">
                @if ($photo)
                    <img src="{{ $photo }}" alt="" class="w-full h-full object-cover">
                @endif
            </div>
            <div class="flex-1">
                <p class="font-mono text-[11px] uppercase tracking-widest text-ink-soft">{{ $order->vehicle->ref_no }}</p>
                <a href="{{ route('vehicles.show', $order->vehicle->slug) }}" class="font-bold text-toco-navy hover:text-toco-red">{{ $order->vehicle->title }}</a>
            </div>
            <div class="text-right">
                <p class="text-xs text-ink-soft">Amount</p>
                <p class="font-extrabold text-toco-navy text-lg">@money($order->amount_usd)</p>
                <p class="text-[11px] text-ink-soft">${{ number_format((float) $order->amount_usd, 2) }} USD</p>
            </div>
        </div>

        @if ($order->paid_at)
            <div class="bg-white border border-line rounded-sm p-5 text-sm space-y-1">
                <p><span class="text-ink-soft">Paid:</span> {{ $order->paid_at->format('d M Y H:i') }}</p>
                @if ($order->shipped_at)<p><span class="text-ink-soft">Shipped:</span> {{ $order->shipped_at->format('d M Y') }}</p>@endif
                @if ($order->delivered_at)<p><span class="text-ink-soft">Delivered:</span> {{ $order->delivered_at->format('d M Y') }}</p>@endif
            </div>
        @endif

        <div class="bg-white border border-line rounded-sm p-5">
            <h2 class="font-bold text-toco-navy mb-3">Messages</h2>
            <p class="text-sm text-ink-soft">Order messaging arrives in Sprint 4.</p>
        </div>
    </section>
</x-layouts.site>
