<aside class="space-y-4">
    @if ($case->vehicle)
        @php($photo = $case->vehicle->primaryPhotoUrl())
        <div class="bg-white border border-line rounded-sm overflow-hidden">
            @if ($photo)<img src="{{ $photo }}" alt="" class="w-full aspect-[4/3] object-cover">@endif
            <div class="p-4 text-sm">
                <p class="font-mono text-[11px] uppercase tracking-widest text-ink-soft">Stock no. {{ $case->vehicle->stock_no }}</p>
                <a href="{{ route('vehicles.show', $case->vehicle->slug) }}" class="font-bold text-toco-navy hover:text-toco-red">{{ trim($case->vehicle->title) }}</a>
                @if ($case->destPort)<p class="text-ink-soft mt-1">To {{ $case->destPort->name }}</p>@endif
                @if ($case->value_usd)<p class="font-extrabold text-toco-navy mt-1">US$ {{ number_format((float) $case->value_usd) }} {{ $case->value_basis }}</p>@endif
            </div>
        </div>
    @endif

    <div class="bg-white border border-line rounded-sm p-4 text-sm">
        <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Your TOCO contact</p>
        <p class="font-bold text-toco-navy text-base mt-1">{{ $contact }}</p>
        <p class="text-ink-soft mt-1">Write below, or reply to any of our emails.</p>
    </div>

    @if ($documents->isNotEmpty())
        <div class="bg-white border border-line rounded-sm p-4 text-sm">
            <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-2">Documents</p>
            <ul class="space-y-1.5">
                @foreach ($documents as $doc)
                    <li><a href="{{ route('crm.portal.document', [$case->reference, $token, $doc->id]) }}" class="text-toco-navy font-semibold hover:text-toco-red">↓ {{ $doc->getCustomProperty('label') ?: $doc->file_name }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif
</aside>
