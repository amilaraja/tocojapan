@php
    use App\Modules\Crm\Domain\Crm;
    use App\Modules\Crm\Support\CrmUi;
    $th = 'text-align:left;padding:8px;font-size:12px;opacity:.7;white-space:nowrap';
    $td = 'padding:8px;border-top:1px solid rgba(127,127,127,.15);font-size:14px;white-space:nowrap';
@endphp
<x-filament-panels::page>
    @include('crm::filament.partials.period')
    <x-filament::section>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">Staff</th><th style="{{ $th }}">Open</th>
                    @foreach (Crm::STAGES as $n => $name)<th style="{{ $th }}" title="{{ $name }}">{{ sprintf('%02d', $n) }}</th>@endforeach
                    <th style="{{ $th }}">Avg first reply</th><th style="{{ $th }}">Replies on time</th><th style="{{ $th }}">Overdue follow-ups</th>
                    <th style="{{ $th }}">Stale</th><th style="{{ $th }}">Last activity</th><th style="{{ $th }}">Won this month</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->rows() as $r)
                        <tr>
                            <td style="{{ $td }};font-weight:700">{{ $r['user']->name }}</td>
                            <td style="{{ $td }}">{{ $r['open'] }}</td>
                            @foreach (Crm::STAGES as $n => $name)<td style="{{ $td }}">{{ $r['by_stage'][$n] ?? 0 }}</td>@endforeach
                            <td style="{{ $td }}">{{ $r['avg_first_response'] === null ? '—' : ($r['avg_first_response'] >= 60 ? round($r['avg_first_response'] / 60, 1).' h' : $r['avg_first_response'].' min') }}</td>
                            <td style="{{ $td }};font-weight:700;color:{{ $r['sla_percent'] === null ? 'inherit' : ($r['sla_percent'] >= 90 ? '#16a34a' : ($r['sla_percent'] >= 70 ? '#d97706' : '#dc2626')) }}">{{ $r['sla_percent'] === null ? '—' : $r['sla_percent'].' %' }}</td>
                            <td style="{{ $td }};color:{{ $r['overdue_follow_ups'] ? '#dc2626' : 'inherit' }}">{{ $r['overdue_follow_ups'] }}</td>
                            <td style="{{ $td }};color:{{ $r['stale'] ? '#d97706' : 'inherit' }}">{{ $r['stale'] }}</td>
                            <td style="{{ $td }}">{{ $r['last_activity'] ? CrmUi::ago(\Illuminate\Support\Carbon::parse($r['last_activity'])) : '—' }}</td>
                            <td style="{{ $td }};font-weight:700">{{ $r['won_month'] }}</td>
                        </tr>
                    @empty
                        <tr><td style="{{ $td }}" colspan="14">No staff yet. Give staff the CRM Agent, Operations or Admin role on the Users screen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
