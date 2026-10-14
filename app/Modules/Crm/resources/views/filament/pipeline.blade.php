@php
    use App\Modules\Crm\Domain\Crm;
    use App\Modules\Crm\Domain\SlaClock;
    use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
    use App\Modules\Crm\Support\CrmUi;
    $columns = $this->columns();
@endphp
<x-filament-panels::page>
    @include('crm::filament.partials.theme')

    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
        @foreach ($this->filterOptions() as $name => $options)
            <div style="min-width:170px">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="{{ $name }}">
                        @foreach ($options as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endforeach
        <div><x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper></div>
        <div><x-filament::input.wrapper><x-filament::input type="date" wire:model.live="until" /></x-filament::input.wrapper></div>
    </div>

    <div wire:poll.60s class="crm-board">
        @foreach ($columns as $stage => $cases)
            <div class="crm-col" style="--c:{{ CrmUi::stageHex($stage) }}">
                <div class="crm-col-head">
                    <div class="n">STAGE {{ sprintf('%02d', $stage) }}</div>
                    <div class="t">{{ Crm::STAGES[$stage] }}</div>
                    <div class="s"><b>{{ $cases->count() }}</b><span>US$ {{ number_format((float) $cases->sum('value_usd')) }}</span></div>
                </div>
                <div class="crm-col-body">
                    @forelse ($cases as $case)
                        @php
                            $days = $case->stage_entered_at ? (int) $case->stage_entered_at->diffInDays() : null;
                        @endphp
                        <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" class="crm-card" style="--sla:{{ CrmUi::slaHex(SlaClock::colour($case)) }};--c:{{ CrmUi::stageHex($stage) }}">
                            <div style="display:flex;justify-content:space-between;gap:6px;align-items:baseline">
                                <b style="font-size:13px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $case->customer?->displayName() }}</b>
                                <span class="crm-ref" style="font-size:10.5px;white-space:nowrap">{{ \Illuminate\Support\Str::after($case->reference, 'C-') }}</span>
                            </div>
                            <div style="font-size:12.5px;margin-top:3px">{{ $case->vehicle ? $case->vehicle->stock_no.' · '.\Illuminate\Support\Str::limit(trim($case->vehicle->title), 30) : '—' }}</div>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;margin-top:7px">
                                <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;min-width:0">
                                    <span class="crm-avatar" style="--c:{{ CrmUi::personHex($case->owner_id) }}">{{ $case->owner ? CrmUi::initials($case->owner->name) : '?' }}</span>
                                    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $case->owner?->name ?? 'Unassigned' }}</span>
                                </span>
                                @if ($case->value_usd)
                                    <span class="crm-soft" style="--c:#059669">US$ {{ number_format((float) $case->value_usd) }}</span>
                                @endif
                            </div>
                            @if ($days !== null)
                                <div style="margin-top:6px"><span class="crm-soft" style="--c:{{ CrmUi::ageHex($days) }}">{{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }} in stage</span></div>
                            @endif
                        </a>
                    @empty
                        <div class="crm-empty">No sales in this stage</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
    <div class="crm-muted" style="display:flex;flex-wrap:wrap;gap:14px">
        <span>Card edge: <span class="crm-soft" style="--c:#16a34a">on time</span> <span class="crm-soft" style="--c:#d97706">due soon</span> <span class="crm-soft" style="--c:#dc2626">late</span></span>
        <span>Days in stage: green under 3, amber under 7, red after.</span>
    </div>
</x-filament-panels::page>
