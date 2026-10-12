{{-- TOCO CRM: My purchases (CRM-POR-001). --}}
<x-layouts.account title="My Purchases — Toco Japan" heading="My Purchases" active="purchases">
    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-sm px-4 py-3">{{ session('status') }}</div>
        @endif

        @forelse ($cases as $case)
            @php($photo = $case->vehicle?->primaryPhotoUrl())
            <a href="{{ $case->portalUrl() }}" class="block bg-white border border-line hover:border-toco-red rounded-sm p-4 transition">
                <div class="flex items-center gap-4">
                    <div class="w-28 h-20 bg-toco-silver-2 rounded-sm overflow-hidden shrink-0">
                        @if ($photo)<img src="{{ $photo }}" alt="" class="w-full h-full object-cover" loading="lazy">@endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-mono text-[11px] uppercase tracking-widest text-ink-soft">{{ $case->reference }}</p>
                        <p class="font-bold text-toco-navy truncate">{{ $case->vehicle ? trim($case->vehicle->title) : ($case->subject ?: \App\Modules\Crm\Domain\Crm::TYPES[$case->type]) }}</p>
                        @if ($next[$case->id])<p class="text-sm text-ink-soft mt-0.5">{{ $next[$case->id] }}</p>@endif
                    </div>
                    <div class="text-right shrink-0">
                        @if ($case->isVehicleSale())
                            <p class="text-xs text-ink-soft">Step</p>
                            <p class="font-extrabold text-toco-navy">{{ sprintf('%02d', $case->stage) }} / 05</p>
                        @else
                            <p class="text-xs font-semibold text-ink-soft">{{ $case->progressLabel() }}</p>
                        @endif
                    </div>
                </div>
                @if ($case->isVehicleSale())
                    <div class="mt-3 grid grid-cols-5 gap-1">
                        @foreach (\App\Modules\Crm\Domain\Crm::STAGES as $n => $name)
                            <div class="h-1.5 rounded-full {{ $case->state === 'won' || $n < $case->stage ? 'bg-emerald-600' : ($n === $case->stage ? 'bg-toco-red' : 'bg-toco-silver-2') }}" title="{{ $name }}"></div>
                        @endforeach
                    </div>
                @endif
            </a>
        @empty
            <div class="bg-white border border-line rounded-sm p-8 text-center text-ink-soft">
                No purchases or enquiries yet. <a href="{{ route('vehicles.index') }}" class="text-toco-red font-semibold">Browse vehicles</a> to start one.
            </div>
        @endforelse
    </div>
</x-layouts.account>
