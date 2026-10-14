@php
    $th = 'text-align:left;padding:6px 8px;font-size:12px;opacity:.7';
    $td = 'padding:6px 8px;border-top:1px solid rgba(127,127,127,.15);font-size:14px';
@endphp
<x-filament-panels::page>
    @include('crm::filament.partials.theme')
    @include('crm::filament.partials.period')
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px;align-items:start">
        @foreach ($this->tables() as $key => $table)
            <div class="crm-accent" style="--c:{{ \App\Modules\Crm\Support\CrmUi::PALETTE[$loop->index % count(\App\Modules\Crm\Support\CrmUi::PALETTE)] }}"><x-filament::section>
                <x-slot name="heading"><span class="crm-h"><span class="dot"></span>{{ $table['title'] }}</span></x-slot>
                <x-slot name="afterHeader"><x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="export('{{ $key }}')">CSV</x-filament::button></x-slot>
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>@foreach ($table['head'] as $h)<th style="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($table['rows'] as $row)
                            <tr>@foreach ($row as $i => $cell)<td style="{{ $td }}{{ $i === 0 ? ';font-weight:600' : '' }}">{{ $cell }}</td>@endforeach</tr>
                        @empty
                            <tr><td style="{{ $td }}" colspan="{{ count($table['head']) }}">No data in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-filament::section></div>
        @endforeach
    </div>
</x-filament-panels::page>
