{{-- Buy / quote actions for one vehicle. Shared by the detail page's price
     card and the listing quick-view modal so the two never disagree.
     Params: $vehicle, $quoteHref (where "Request a quote" points; a same-page
     "#…" target opens the detail page's quote modal instead of scrolling),
     $portExpr (optional JS expression inside a cifCalc() scope, e.g. 'portId',
     so the proforma link follows the port picked in the calculator).
     Icons: PayPal mark (Simple Icons, CC0); others Lucide (ISC). --}}
@php
    $payment = app(\App\Settings\PaymentSettings::class);
    $paypalMode = config('paypal.mode', 'sandbox');
    $paypalReady = $payment->paypal_enabled
        && ! empty(config("paypal.{$paypalMode}.client_id"))
        && ! empty(config("paypal.{$paypalMode}.client_secret"));
    $bankReady = $payment->bank_transfer_enabled;
    $isSold = $vehicle->status === 'sold';
    // Reserved: on hold for a buyer. Only that buyer may continue (UI brief 7.1).
    $isReserved = $vehicle->isReserved();
    $heldByMe = $isReserved && app(\App\Services\CheckoutFlow::class)->heldByCurrentBuyer($vehicle);
    $buyable = ! $isSold && (! $isReserved || $heldByMe) && ! $vehicle->price_on_request && $vehicle->effectivePriceFob() > 0 && $vehicle->canCheckoutOnline();
    // LC proforma: priced, has M3, not quote-only partner stock, and some destination accepts LC.
    $showProforma = ! $isSold && (! $isReserved || $heldByMe)
        && app(\App\Services\ProformaInvoiceService::class)->vehicleError($vehicle) === null
        && \App\Models\ImportRegulation::anyLcDestination();
    $proformaUrl = route('proforma.create', array_filter(['slug' => $vehicle->slug, 'port_id' => ($destPort ?? null)?->id]));
    $portExpr = $portExpr ?? null;
    $btn = 'w-full inline-flex items-center justify-center gap-2 font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm transition';
@endphp
@if (session('vehicle_reserved'))
    {{-- Checkout race lost (UI brief 7.2): a friendly message, not an error. --}}
    <div class="w-full text-sm border rounded-sm px-4 py-3 mb-2" style="background:#FFFBEB;border-color:#F59E0B;color:#78350F" role="status">
        Someone else just reserved this vehicle. You can keep browsing; if their payment does not arrive, it will come back on sale.
    </div>
@endif
@if ($isSold)
    <div class="w-full text-center bg-toco-red text-white border border-toco-red font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
        Sold — view our available stock <a href="{{ route('vehicles.index') }}" class="underline">here</a>
    </div>
@elseif ($isReserved && ! $heldByMe)
    <div class="w-full text-center text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm" style="background:#B45309">
        Under offer
    </div>
    <p class="text-xs text-ink-soft text-center">Reserved for another buyer. If the reservation lapses, it returns to sale automatically.</p>
    <a href="{{ $quoteHref }}" @if (str_starts_with($quoteHref, '#')) x-data @click.prevent="$dispatch('open-quote')" @endif class="{{ $btn }} bg-white border-2 border-toco-navy text-toco-navy hover:bg-toco-navy hover:text-white">
        Ask about similar cars
    </a>
@else
@auth
    @if ($buyable && $paypalReady)
        <form method="GET" action="{{ route('checkout.paypal.show', $vehicle->slug) }}">
            <button type="submit" class="{{ $btn }} bg-toco-navy hover:bg-toco-navy-deep text-white">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.016 19.198h-4.2a.562.562 0 0 1-.555-.65L5.093.584A.692.692 0 0 1 5.776 0h7.222c3.417 0 5.904 2.488 5.846 5.5-.006.25-.027.5-.066.747A6.794 6.794 0 0 1 12.071 12H8.743a.69.69 0 0 0-.682.583l-.325 2.056-.013.083-.692 4.39-.015.087zM19.79 6.142c-.01.087-.01.175-.023.261a7.76 7.76 0 0 1-7.695 6.598H9.007l-.283 1.795-.013.083-.692 4.39-.134.843-.014.088H6.86l-.497 3.15a.562.562 0 0 0 .555.65h3.612c.34 0 .63-.249.683-.585l.952-6.031a.692.692 0 0 1 .683-.584h2.126a6.793 6.793 0 0 0 6.707-5.752c.306-1.95-.466-3.744-1.89-4.906z"/></svg>
                Buy with PayPal — @money($vehicle->effectivePriceFob() ?? $vehicle->price_fob)
            </button>
        </form>
    @endif
    @if ($buyable && $bankReady)
        <a href="{{ route('checkout.bank.show', $vehicle->slug) }}" class="{{ $btn }} bg-toco-navy hover:bg-toco-navy-deep text-white">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 22h18M6 18v-7M10 18v-7M14 18v-7M18 18v-7M12 2l8 5H4z"/></svg>
            Buy with bank transfer (TT)
        </a>
    @endif
    @if ($buyable && ! $paypalReady && ! $bankReady)
        <div class="w-full text-center bg-toco-silver-2 text-ink-soft border border-dashed border-line font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">
            Buy now — coming soon
        </div>
    @endif
    @error('paypal')
        <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">{{ $message }}</div>
    @enderror
    @error('payment')
        <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">{{ $message }}</div>
    @enderror
    @error('vehicle')
        <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">{{ $message }}</div>
    @enderror
    <a href="{{ $quoteHref }}" @if (str_starts_with($quoteHref, '#')) x-data @click.prevent="$dispatch('open-quote')" @endif class="{{ $btn }} bg-toco-red hover:bg-toco-red-deep text-white">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M13 8H7M17 12H7"/></svg>
        Request a quote
    </a>
@else
    <a href="{{ route('login') }}" class="block text-center bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-3 rounded-sm">Sign in to buy or quote</a>
@endauth
@if ($showProforma)
    {{-- Guests are sent to sign in, then straight back to the proforma form. --}}
    <a href="{{ $proformaUrl }}"
       @if ($portExpr) :href="{{ $portExpr }} ? @js(route('proforma.create', $vehicle->slug)) + '?port_id=' + {{ $portExpr }} : @js($proformaUrl)" @endif
       class="{{ $btn }} bg-white border-2 border-toco-navy text-toco-navy hover:bg-toco-navy hover:text-white">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M14 8H8M16 12H8M13 16H8"/></svg>
        Get a Proforma Invoice
    </a>
@endif
@endif
