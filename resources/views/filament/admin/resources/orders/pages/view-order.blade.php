<x-filament-panels::page>
    @php
        $order = $this->record;
        $order->load(['user', 'vehicle.media', 'messages.user', 'messages.media']);
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Order summary --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-5">
                <p class="text-[11px] uppercase tracking-widest font-mono text-gray-400">{{ $order->order_no }}</p>
                <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $order->vehicle->title }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ $order->vehicle->ref_no }}</p>

                @php($photo = $order->vehicle->getFirstMediaUrl('photos'))
                @if ($photo)
                    <a href="{{ url('/vehicles/'.$order->vehicle->slug) }}" target="_blank" class="block mt-3 aspect-[16/10] bg-gray-100 rounded-lg overflow-hidden">
                        <img src="{{ $photo }}" alt="" class="w-full h-full object-cover">
                    </a>
                @endif

                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Customer</dt><dd class="font-medium">{{ $order->user->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $order->user->email }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Amount</dt><dd class="font-bold text-emerald-600">${{ number_format((float) $order->amount_usd, 2) }} USD</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd class="font-semibold">{{ $order->statusLabel() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Created</dt><dd>{{ $order->created_at->format('d M Y H:i') }}</dd></div>
                    @if ($order->paid_at)<div class="flex justify-between"><dt class="text-gray-500">Paid</dt><dd>{{ $order->paid_at->format('d M Y H:i') }}</dd></div>@endif
                    @if ($order->paypal_capture_id)<div class="flex justify-between"><dt class="text-gray-500">PayPal capture</dt><dd class="font-mono text-xs">{{ $order->paypal_capture_id }}</dd></div>@endif
                </dl>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-800">
                Status transitions (Mark as shipped, delivered etc.) and email notifications ship in Sprint 5.
            </div>
        </div>

        {{-- Thread + reply --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-5">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Conversation with {{ $order->user->name }}</h3>

                @if ($order->messages->isEmpty())
                    <p class="text-sm text-gray-400 mb-4">No messages yet. Reply below to start the thread.</p>
                @else
                    <div class="space-y-3 mb-4 max-h-[520px] overflow-y-auto pr-1">
                        @foreach ($order->messages as $m)
                            <div class="flex {{ $m->from_admin ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] {{ $m->from_admin ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900' }} rounded-lg px-3.5 py-2 text-sm">
                                    <p class="text-[10px] uppercase tracking-widest opacity-70 mb-0.5">
                                        {{ $m->from_admin ? ($m->user->name ?? 'Admin') : ($m->user->name ?? 'Customer') }} · {{ $m->created_at->diffForHumans() }}
                                    </p>
                                    @if ($m->body)
                                        <div class="whitespace-pre-wrap">{{ $m->body }}</div>
                                    @endif
                                    @php($attachments = $m->getMedia('attachments'))
                                    @if ($attachments->isNotEmpty())
                                        <ul class="mt-2 space-y-1">
                                            @foreach ($attachments as $att)
                                                <li>
                                                    <a href="{{ $att->getUrl() }}" target="_blank" class="inline-flex items-center gap-1.5 text-[12px] underline opacity-90 hover:opacity-100">
                                                        <x-heroicon-o-paper-clip class="w-3 h-3" />
                                                        {{ $att->file_name }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form wire:submit="sendReply" class="space-y-4">
                    {{ $this->replyForm }}
                    <div class="flex justify-end">
                        <x-filament::button type="submit" color="primary">Send reply</x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
