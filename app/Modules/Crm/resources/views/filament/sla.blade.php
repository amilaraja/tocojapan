@php
    use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
    use App\Modules\Crm\Models\CrmSlaTimer;
    use App\Modules\Crm\Support\CrmUi;
    $t = $this->timers();
    $row = 'display:grid;grid-template-columns:110px 1fr 140px 150px;gap:10px;padding:8px 0;border-top:1px solid rgba(127,127,127,.15);font-size:14px;align-items:center';
@endphp
<x-filament-panels::page>
    <div wire:poll.60s style="display:grid;gap:16px">
        @foreach (['breached' => ['Breached', '#dc2626'], 'risk' => ['At risk (past 75 %)', '#d97706']] as $key => [$heading, $colour])
            <x-filament::section>
                <x-slot name="heading"><span style="color:{{ $colour }}">{{ $heading }} · {{ $t[$key]->count() }}</span></x-slot>
                @forelse ($t[$key] as $timer)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $timer->case_id]) }}" style="{{ $row }}">
                        <b>{{ $timer->case->reference }}</b>
                        <span>{{ $timer->case->customer?->displayName() }}{{ $timer->case->vehicle ? ' · '.$timer->case->vehicle->stock_no : '' }}<br><span style="opacity:.65;font-size:12px">{{ CrmSlaTimer::MEASURES[$timer->measure] ?? $timer->measure }}</span></span>
                        <span>{{ $timer->case->owner?->name ?? 'Unassigned' }}</span>
                        <span style="color:{{ $colour }};font-weight:700">{{ $key === 'breached' ? 'late '.$timer->due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) : $timer->due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE).' left' }}</span>
                    </a>
                @empty
                    <div style="opacity:.65;font-size:13px">None.</div>
                @endforelse
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
