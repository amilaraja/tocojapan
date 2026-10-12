@php
    use App\Modules\Crm\Domain\Crm;
    use App\Modules\Crm\Domain\SlaClock;
    use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
    use App\Modules\Crm\Support\CrmUi;
    $columns = $this->columns();
    $muted = 'opacity:.65;font-size:12px';
@endphp
<x-filament-panels::page>
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

    <div wire:poll.60s style="display:grid;grid-template-columns:repeat(5,minmax(220px,1fr));gap:10px;overflow-x:auto;padding-bottom:8px">
        @foreach ($columns as $stage => $cases)
            <div style="background:rgba(127,127,127,.07);border-radius:10px;padding:8px;min-height:200px">
                <div style="font-weight:800;padding:4px 6px">{{ Crm::stageLabel($stage) }} <span style="{{ $muted }}">· {{ $cases->count() }} · US$ {{ number_format((float) $cases->sum('value_usd')) }}</span></div>
                @foreach ($cases as $case)
                    @php $c = SlaClock::colour($case); @endphp
                    <a href="{{ CaseResource::getUrl('view', ['record' => $case]) }}" style="display:block;background:rgba(127,127,127,.08);color:inherit;border:1px solid rgba(127,127,127,.25);border-left:4px solid {{ CrmUi::slaHex($c) }};border-radius:8px;padding:8px 10px;margin-top:8px">
                        <div style="display:flex;justify-content:space-between;gap:6px"><b style="font-size:13px">{{ $case->customer?->displayName() }}</b><span style="{{ $muted }}">{{ $case->reference }}</span></div>
                        <div style="font-size:13px;margin-top:2px">{{ $case->vehicle ? $case->vehicle->stock_no.' · '.\Illuminate\Support\Str::limit(trim($case->vehicle->title), 28) : '—' }}</div>
                        <div style="display:flex;justify-content:space-between;gap:6px;margin-top:4px;{{ $muted }}">
                            <span>{{ $case->owner?->name ?? 'Unassigned' }}</span>
                            <span>{{ $case->value_usd ? 'US$ '.number_format((float) $case->value_usd) : '' }}</span>
                        </div>
                        <div style="{{ $muted }}">{{ $case->stage_entered_at ? (int) $case->stage_entered_at->diffInDays().' days in stage' : '' }}</div>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
