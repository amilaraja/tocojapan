@php
    $tx = $content['testimonials'] ?? [];
    $txKicker = $tx['kicker'] ?? 'Worldwide deliveries';
    $txHeadline = $tx['headline'] ?? 'Customers in 90+ countries.';
    $txBody = $tx['body'] ?? 'Photographs from real deliveries — uploaded by customers and our shipping partners.';
@endphp

@if ($testimonials->isNotEmpty())
<section id="testimonials" class="bg-surface">
    <div class="max-w-[1600px] mx-auto px-6 2xl:px-8 py-16">
        <div class="max-w-2xl mb-8">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $txKicker }}</p>
            <h2 class="text-2xl md:text-3xl font-extrabold text-toco-navy mt-1 leading-tight">{{ $txHeadline }}</h2>
            <p class="text-sm text-ink-soft mt-3">{{ $txBody }}</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($testimonials as $r)
                @php
                    $displayName = $r->name ?: 'Verified customer';
                    $line2 = trim(($r->flag ? $r->flag.' ' : '').($r->country ?? ''));
                @endphp
                <figure class="bg-white border border-line rounded-sm overflow-hidden flex flex-col">
                    <div class="aspect-[4/3] bg-toco-silver-2 overflow-hidden">
                        @if ($r->getPhotoUrl())
                            <img src="{{ $r->getPhotoUrl() }}" alt="Delivery: {{ $r->vehicle_label ?: $displayName }}" class="w-full h-full object-cover block">
                        @endif
                    </div>
                    <figcaption class="px-3 pt-2.5 pb-3 flex flex-col gap-0.5">
                        @if ($r->vehicle_label)
                            <div class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">{{ $r->vehicle_label }}</div>
                        @endif
                        <div class="text-[13px] font-bold text-ink leading-tight">{{ $displayName }}</div>
                        @if ($line2 !== '')
                            <div class="text-[11px] text-ink-soft font-medium">{!! $line2 !!}</div>
                        @endif
                        <div class="text-[11px] text-amber-500 tracking-[0.12em] mt-0.5">{{ str_repeat('★', $r->stars).str_repeat('☆', 5 - $r->stars) }}</div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif
