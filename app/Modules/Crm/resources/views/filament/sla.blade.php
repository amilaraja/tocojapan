@php
    use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
    use App\Modules\Crm\Models\CrmSlaTimer;
    use App\Modules\Crm\Support\CrmUi;
    $t = $this->timers();
    $row = 'display:grid;grid-template-columns:120px 1fr 170px 150px;justify-content:stretch';
@endphp
<x-filament-panels::page>
    @include('crm::filament.partials.theme')
    <div wire:poll.60s style="display:grid;gap:16px">
        @foreach (['breached' => ['Late (deadline missed)', '#dc2626'], 'risk' => ['At risk (past 75 %)', '#f59e0b']] as $key => [$heading, $colour])
            <div class="crm-accent" style="--c:{{ $colour }}"><x-filament::section>
                <x-slot name="heading"><span class="crm-h"><span class="dot"></span>{{ $heading }}</span> <span class="crm-count">{{ $t[$key]->count() }}</span></x-slot>
                @forelse ($t[$key] as $timer)
                    <a href="{{ CaseResource::getUrl('view', ['record' => $timer->case_id]) }}" class="crm-row" style="{{ $row }}">
                        <b class="crm-ref">{{ $timer->case->reference }}</b>
                        <span>{{ $timer->case->customer?->displayName() }}{{ $timer->case->vehicle ? ' · '.$timer->case->vehicle->stock_no : '' }}<br><span style="opacity:.65;font-size:12px">{{ CrmSlaTimer::MEASURES[$timer->measure] ?? $timer->measure }}</span></span>
                        <span style="display:inline-flex;align-items:center;gap:6px"><span class="crm-avatar" style="--c:{{ CrmUi::personHex($timer->case->owner_id) }}">{{ $timer->case->owner ? CrmUi::initials($timer->case->owner->name) : '?' }}</span>{{ $timer->case->owner?->name ?? 'Unassigned' }}</span>
                        <span class="crm-chip" style="--c:{{ $colour }};justify-self:start">{{ $key === 'breached' ? 'late '.$timer->due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) : $timer->due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE).' left' }}</span>
                    </a>
                @empty
                    <div style="opacity:.65;font-size:13px">None.</div>
                @endforelse
            </x-filament::section></div>
        @endforeach
    </div>
</x-filament-panels::page>
