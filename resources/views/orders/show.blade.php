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

        @if ($order->ship_to_name || $order->destPort)
            <div class="bg-white border border-line rounded-sm p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div>
                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-1">Ship to</p>
                    @if ($order->ship_to_name)<p class="font-bold text-toco-navy">{{ $order->ship_to_name }}</p>@endif
                    @if ($order->ship_to_address_line1)<p>{{ $order->ship_to_address_line1 }}</p>@endif
                    @if ($order->ship_to_address_line2)<p>{{ $order->ship_to_address_line2 }}</p>@endif
                    <p>{{ collect([$order->ship_to_city, $order->ship_to_state, $order->ship_to_postcode])->filter()->implode(', ') }}</p>
                    @if ($order->destCountry)<p>{{ $order->destCountry->name }}</p>@endif
                    @if ($order->ship_to_phone)<p class="text-ink-soft mt-1">{{ $order->ship_to_phone }}</p>@endif
                </div>
                @if ($order->cif_total)
                    <div>
                        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-1">CIF to {{ $order->destPort?->name }}</p>
                        <dl class="space-y-1">
                            <div class="flex justify-between"><dt class="text-ink-soft">FOB</dt><dd class="tabular-nums">${{ number_format((float) max(0, $order->amount_usd - $order->cif_freight - $order->cif_insurance), 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-ink-soft">Freight</dt><dd class="tabular-nums">${{ number_format((float) $order->cif_freight, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-ink-soft">Insurance</dt><dd class="tabular-nums">${{ number_format((float) $order->cif_insurance, 2) }}</dd></div>
                            <div class="flex justify-between border-t pt-1 font-bold"><dt>CIF total</dt><dd class="tabular-nums">${{ number_format((float) $order->cif_total, 2) }}</dd></div>
                        </dl>
                    </div>
                @endif
            </div>
        @endif

        @if ($order->payment_provider === 'bank_transfer' && $order->status === 'pending')
            <div class="bg-amber-50 border border-amber-200 rounded-sm p-5">
                <p class="font-mono text-[10px] uppercase tracking-widest text-amber-800 font-bold">Bank transfer instructions</p>
                <h2 class="font-bold text-toco-navy text-base mt-1 mb-3">Please send your payment to:</h2>
                @php($bankDetails = app(\App\Settings\PaymentSettings::class)->bank_account_details)
                @if (filled($bankDetails))
                    <div class="prose prose-sm max-w-none bg-white border border-line rounded p-4">{!! $bankDetails !!}</div>
                @else
                    <p class="text-sm text-ink-soft bg-white border border-line rounded p-4">Bank details have not been published yet. Please contact us.</p>
                @endif
                <div class="mt-3 text-sm">
                    <p><span class="text-ink-soft">Amount:</span> <strong>${{ number_format((float) $order->amount_usd, 2) }} USD</strong></p>
                    <p><span class="text-ink-soft">Reference (include on transfer):</span> <strong class="font-mono">{{ $order->order_no }}</strong></p>
                </div>
                <p class="text-xs text-ink-soft mt-3">Once the funds arrive we'll mark this order as paid and start processing. You can attach the payment receipt in the message thread below.</p>
            </div>
        @endif

        @if ($order->paid_at)
            <div class="bg-white border border-line rounded-sm p-5 text-sm space-y-1">
                <p><span class="text-ink-soft">Paid:</span> {{ $order->paid_at->format('d M Y H:i') }}</p>
                @if ($order->shipped_at)<p><span class="text-ink-soft">Shipped:</span> {{ $order->shipped_at->format('d M Y') }}</p>@endif
                @if ($order->delivered_at)<p><span class="text-ink-soft">Delivered:</span> {{ $order->delivered_at->format('d M Y') }}</p>@endif
            </div>
        @endif

        @if ($order->bl_number || $order->vessel_name)
            <div class="bg-white border border-line rounded-sm p-5">
                <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Shipping details</p>
                <h2 class="font-bold text-toco-navy text-base mt-1 mb-3">Vessel & B/L</h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    @if ($order->bl_number)<div><dt class="text-ink-soft text-[11px] uppercase tracking-widest">B/L number</dt><dd class="font-mono font-bold text-toco-navy">{{ $order->bl_number }}</dd></div>@endif
                    @if ($order->vessel_name)<div><dt class="text-ink-soft text-[11px] uppercase tracking-widest">Vessel</dt><dd class="font-semibold">{{ $order->vessel_name }}</dd></div>@endif
                    @if ($order->voyage_no)<div><dt class="text-ink-soft text-[11px] uppercase tracking-widest">Voyage</dt><dd class="font-semibold">{{ $order->voyage_no }}</dd></div>@endif
                    @if ($order->eta_at)<div><dt class="text-ink-soft text-[11px] uppercase tracking-widest">ETA</dt><dd class="font-semibold">{{ $order->eta_at->format('d M Y') }}</dd></div>@endif
                </dl>
                @if ($order->carrier_tracking_url)
                    <a href="{{ $order->carrier_tracking_url }}" target="_blank" class="inline-flex items-center gap-1.5 mt-3 text-xs text-toco-red hover:underline">
                        Track vessel on carrier site
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M7 7h10v10"/></svg>
                    </a>
                @endif
                <p class="text-[11px] text-ink-soft mt-3">
                    Tip: search the vessel name on <a href="https://www.marinetraffic.com" target="_blank" class="underline">MarineTraffic</a> or
                    <a href="https://www.vesselfinder.com" target="_blank" class="underline">VesselFinder</a> to see live position.
                </p>
            </div>
        @endif

        <div class="bg-white border border-line rounded-sm p-5">
            <h2 class="font-bold text-toco-navy mb-3">Messages</h2>

            @if (session('status'))
                <div class="mb-3 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{{ session('status') }}</div>
            @endif

            @if ($order->messages->isEmpty())
                <p class="text-sm text-ink-soft mb-4">Send the Toco team a message about this order — questions, shipping address, anything.</p>
            @else
                <div class="space-y-3 mb-5 max-h-[480px] overflow-y-auto pr-1">
                    @foreach ($order->messages as $m)
                        <div class="flex {{ $m->from_admin ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[80%] {{ $m->from_admin ? 'bg-toco-silver-2 text-ink' : 'bg-toco-navy text-white' }} rounded-lg px-3.5 py-2 text-sm">
                                <p class="text-[10px] uppercase tracking-widest opacity-70 mb-0.5">
                                    {{ $m->from_admin ? 'Toco team' : 'You' }} · {{ $m->created_at->diffForHumans() }}
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
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>
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

            <form method="POST" action="{{ route('orders.messages.store', $order) }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <textarea name="body" rows="3" placeholder="Type your message..." class="w-full border-line rounded-sm text-sm">{{ old('body') }}</textarea>
                @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <div class="flex items-center justify-between gap-3">
                    <label class="text-xs text-ink-soft inline-flex items-center gap-1.5 cursor-pointer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 11.05-9.06 9.06a5.5 5.5 0 0 1-7.78-7.78l9.06-9.06a4 4 0 0 1 5.66 5.66l-9.06 9.07a2.5 2.5 0 0 1-3.54-3.54l8.36-8.36"/></svg>
                        Attach files
                        <input type="file" name="attachments[]" multiple class="hidden" onchange="document.getElementById('atc').textContent = this.files.length ? this.files.length + ' file(s) selected' : ''">
                    </label>
                    <span id="atc" class="text-xs text-ink-soft"></span>
                    <button type="submit" class="ml-auto bg-toco-red hover:bg-toco-red-deep text-white text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-sm">Send</button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
