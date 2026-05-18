<x-layouts.account :title="'My orders — Toco Japan'" heading="My Orders" active="orders">
    @if ($orders->isEmpty())
        <div class="bg-white border border-line rounded-sm p-10 text-center">
            <p class="text-ink-soft">You haven't placed any orders yet.</p>
            <a href="{{ route('vehicles.index') }}" class="inline-block mt-4 bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">Browse vehicles</a>
        </div>
    @else
        <div class="bg-white border border-line rounded-sm divide-y divide-line">
            @foreach ($orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="flex items-center gap-4 p-4 hover:bg-toco-silver-2">
                    @php($photo = $order->vehicle->getFirstMediaUrl('photos'))
                    <div class="w-20 h-14 bg-toco-silver-2 rounded-sm overflow-hidden shrink-0">
                        @if ($photo)
                            <img src="{{ $photo }}" alt="" class="w-full h-full object-cover">
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-mono text-[11px] text-ink-soft uppercase tracking-widest">{{ $order->order_no }}</p>
                        <p class="font-bold text-toco-navy truncate">{{ $order->vehicle->title }}</p>
                        <p class="text-xs text-ink-soft">{{ $order->created_at->format('d M Y') }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-extrabold text-toco-navy">@money($order->amount_usd)</p>
                        <span class="inline-block mt-1 text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded {{ $order->isPaid() ? 'bg-green-100 text-green-800' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ $order->statusLabel() }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.account>
