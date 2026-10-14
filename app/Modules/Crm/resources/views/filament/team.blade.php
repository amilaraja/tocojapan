@php
    use App\Modules\Crm\Domain\Crm;
    use App\Modules\Crm\Support\CrmUi;
    $th = 'text-align:left;padding:8px;font-size:12px;opacity:.7;white-space:nowrap';
    $td = 'padding:8px;border-top:1px solid rgba(127,127,127,.15);font-size:14px;white-space:nowrap';
@endphp
<x-filament-panels::page>
    @include('crm::filament.partials.theme')
    @include('crm::filament.partials.period')
    <div class="crm-accent" style="--c:#6366f1"><x-filament::section>
        <x-slot name="heading"><span class="crm-h"><span class="dot"></span>Team results</span></x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">Staff</th><th style="{{ $th }}">Open</th>
                    @foreach (Crm::STAGES as $n => $name)<th style="{{ $th }}" title="{{ $name }}"><span class="crm-chip" style="--c:{{ CrmUi::stageHex($n) }}">{{ sprintf('%02d', $n) }}</span></th>@endforeach
                    <th style="{{ $th }}">Avg first reply</th><th style="{{ $th }}">Replies on time</th><th style="{{ $th }}">Overdue follow-ups</th>
                    <th style="{{ $th }}">Stale</th><th style="{{ $th }}">Last activity</th><th style="{{ $th }}">Won this month</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->rows() as $r)
                        <tr>
                            <td style="{{ $td }};font-weight:700"><span style="display:inline-flex;align-items:center;gap:8px"><span class="crm-avatar" style="--c:{{ CrmUi::personHex($r['user']->id) }}">{{ CrmUi::initials($r['user']->name) }}</span>{{ $r['user']->name }}</span></td>
                            <td style="{{ $td }}"><span class="crm-soft" style="--c:#3b82f6">{{ $r['open'] }}</span></td>
                            @foreach (Crm::STAGES as $n => $name)<td style="{{ $td }}">@if ($r['by_stage'][$n] ?? 0)<span class="crm-soft" style="--c:{{ CrmUi::stageHex($n) }}">{{ $r['by_stage'][$n] }}</span>@else<span class="crm-muted">0</span>@endif</td>@endforeach
                            <td style="{{ $td }}">{{ $r['avg_first_response'] === null ? '—' : ($r['avg_first_response'] >= 60 ? round($r['avg_first_response'] / 60, 1).' h' : $r['avg_first_response'].' min') }}</td>
                            <td style="{{ $td }}">@if ($r['sla_percent'] === null)—@else<span class="crm-chip" style="--c:{{ $r['sla_percent'] >= 90 ? '#16a34a' : ($r['sla_percent'] >= 70 ? '#d97706' : '#dc2626') }}">{{ $r['sla_percent'] }} %</span>@endif</td>
                            <td style="{{ $td }};color:{{ $r['overdue_follow_ups'] ? '#dc2626' : 'inherit' }}">{{ $r['overdue_follow_ups'] }}</td>
                            <td style="{{ $td }};color:{{ $r['stale'] ? '#d97706' : 'inherit' }}">{{ $r['stale'] }}</td>
                            <td style="{{ $td }}">{{ $r['last_activity'] ? CrmUi::ago(\Illuminate\Support\Carbon::parse($r['last_activity'])) : '—' }}</td>
                            <td style="{{ $td }}"><span class="crm-chip" style="--c:{{ $r['won_month'] ? '#10b981' : '#94a3b8' }}">{{ $r['won_month'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td style="{{ $td }}" colspan="14">No staff yet. Give staff the CRM Agent, Operations or Admin role on the Users screen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section></div>
</x-filament-panels::page>
