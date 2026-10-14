@php
    $d = $this->data();
    $stages = $d['stages'];
    $maxStage = max(1, $stages->max('count'));
    $weeks = $d['weeks'];
    $maxWeek = max(1, $weeks->max('new'), $weeks->max('won'));
    $months = $d['months'];
    $maxMonth = max(1, $months->max('value'));
    $sources = $d['sources'];
    $maxSource = max(1, $sources->max('count'));
    $states = $d['states'];
    $stateTotal = max(1, $states->sum('count'));
    $muted = 'opacity:.65';
@endphp
<x-filament-panels::page>
    <style>
        .crmd-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
        .crmd-tile{display:block;border-radius:12px;padding:14px 16px;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.12);transition:transform .15s,box-shadow .15s}
        a.crmd-tile:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.18)}
        .crmd-tile .l{font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;opacity:.9}
        .crmd-tile .v{font-size:30px;font-weight:800;line-height:1.15;margin-top:4px}
        .crmd-tile .n{font-size:12px;opacity:.9;margin-top:2px}
        .crmd-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px;align-items:start}
        .crmd-pipe{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
        .crmd-stage{border-radius:10px;padding:12px;color:#fff;position:relative;overflow:hidden}
        .crmd-stage .num{font-size:11px;font-weight:700;opacity:.85}
        .crmd-stage .nm{font-size:14px;font-weight:700}
        .crmd-stage .c{font-size:28px;font-weight:800;line-height:1.1;margin-top:6px}
        .crmd-stage .val{font-size:12px;opacity:.95}
        .crmd-stage .late{display:inline-block;margin-top:6px;background:rgba(255,255,255,.25);border-radius:999px;padding:1px 8px;font-size:11px;font-weight:700}
        .crmd-stage .bar{position:absolute;left:0;bottom:0;height:5px;background:rgba(255,255,255,.55)}
        .crmd-hbar{display:grid;grid-template-columns:150px 1fr 40px;gap:8px;align-items:center;font-size:13px;margin:6px 0}
        .crmd-hbar .t{height:14px;border-radius:7px;background:rgba(127,127,127,.12);overflow:hidden}
        .crmd-hbar .f{height:100%;border-radius:7px}
        .crmd-legend{display:flex;flex-wrap:wrap;gap:14px;font-size:12px;margin-top:6px}
        .crmd-legend i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:-1px}
        .crmd-att{width:100%;border-collapse:collapse;font-size:13px}
        .crmd-att td{padding:7px 8px;border-top:1px solid rgba(127,127,127,.15)}
        .crmd-pill{display:inline-block;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:700;color:#fff}
        @media (max-width:900px){.crmd-pipe{grid-template-columns:repeat(2,minmax(0,1fr))}.crmd-grid{grid-template-columns:1fr}}
        @media (max-width:640px){.crmd-att td:nth-child(3){display:none}.crmd-att td:last-child{white-space:normal!important}.crmd-att .crmd-pill{margin:1px 0}.crmd-hbar{grid-template-columns:110px 1fr 32px}}
    </style>

    {{-- Headline numbers --}}
    <div class="crmd-tiles">
        @foreach ($d['tiles'] as $t)
            @if ($t['url'])
                <a href="{{ $t['url'] }}" class="crmd-tile" style="background:linear-gradient(135deg,{{ $t['from'] }},{{ $t['to'] }})">
            @else
                <div class="crmd-tile" style="background:linear-gradient(135deg,{{ $t['from'] }},{{ $t['to'] }})">
            @endif
                <div class="l">{{ $t['label'] }}</div>
                <div class="v">{{ $t['value'] }}</div>
                <div class="n">{{ $t['note'] }}</div>
            @if ($t['url'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    {{-- Pipeline strip --}}
    <x-filament::section>
        <x-slot name="heading">Pipeline: open car sales by stage</x-slot>
        <x-slot name="afterHeader"><x-filament::button size="xs" color="gray" tag="a" :href="\App\Modules\Crm\Filament\Pages\PipelineBoard::getUrl()" icon="heroicon-o-view-columns">Open the pipeline board</x-filament::button></x-slot>
        <div class="crmd-pipe">
            @foreach ($stages as $s)
                <a href="{{ \App\Modules\Crm\Filament\Pages\PipelineBoard::getUrl() }}" class="crmd-stage" style="background:linear-gradient(160deg,{{ $s['colour'] }},{{ $s['colour'] }}cc)">
                    <div class="num">STAGE 0{{ $s['n'] }}</div>
                    <div class="nm">{{ $s['name'] }}</div>
                    <div class="c">{{ $s['count'] }}</div>
                    <div class="val">${{ number_format($s['value']) }}</div>
                    @if ($s['late'])
                        <span class="late">{{ $s['late'] }} late</span>
                    @endif
                    <span class="bar" style="width:{{ round($s['count'] * 100 / $maxStage) }}%"></span>
                </a>
            @endforeach
        </div>
    </x-filament::section>

    <div class="crmd-grid">
        {{-- Weekly new vs sold --}}
        <x-filament::section>
            <x-slot name="heading">New Cases and cars sold, last 12 weeks</x-slot>
            @php
                $w = 560; $h = 190; $pad = 24; $slot = ($w - $pad) / 12; $bw = $slot / 2 - 4;
            @endphp
            <svg viewBox="0 0 {{ $w }} {{ $h + 22 }}" style="width:100%;height:auto" role="img" aria-label="New Cases and cars sold per week">
                @foreach ([0, .5, 1] as $g)
                    <line x1="{{ $pad }}" x2="{{ $w }}" y1="{{ $h - $g * ($h - 10) }}" y2="{{ $h - $g * ($h - 10) }}" stroke="currentColor" stroke-opacity=".12"/>
                    <text x="{{ $pad - 6 }}" y="{{ $h - $g * ($h - 10) + 4 }}" font-size="10" text-anchor="end" fill="currentColor" fill-opacity=".55">{{ round($maxWeek * $g) }}</text>
                @endforeach
                @foreach ($weeks as $i => $wk)
                    @php
                        $x = $pad + $i * $slot + 3;
                        $hn = ($h - 10) * $wk['new'] / $maxWeek;
                        $hw = ($h - 10) * $wk['won'] / $maxWeek;
                    @endphp
                    <rect x="{{ $x }}" y="{{ $h - $hn }}" width="{{ $bw }}" height="{{ $hn }}" rx="3" fill="#6366f1"><title>{{ $wk['label'] }}: {{ $wk['new'] }} new</title></rect>
                    <rect x="{{ $x + $bw + 2 }}" y="{{ $h - $hw }}" width="{{ $bw }}" height="{{ $hw }}" rx="3" fill="#10b981"><title>{{ $wk['label'] }}: {{ $wk['won'] }} sold</title></rect>
                    @if ($i % 2 === 0)
                        <text x="{{ $x + $bw }}" y="{{ $h + 16 }}" font-size="10" text-anchor="middle" fill="currentColor" fill-opacity=".6">{{ $wk['label'] }}</text>
                    @endif
                @endforeach
            </svg>
            <div class="crmd-legend"><span><i style="background:#6366f1"></i>New Cases</span><span><i style="background:#10b981"></i>Cars sold</span></div>
        </x-filament::section>

        {{-- Won value by month --}}
        <x-filament::section>
            <x-slot name="heading">Sales value by month (USD)</x-slot>
            @php
                $w = 560; $h = 190; $slot = $w / 6; $bw = $slot - 24;
                $palette = ['#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981'];
            @endphp
            <svg viewBox="0 0 {{ $w }} {{ $h + 22 }}" style="width:100%;height:auto" role="img" aria-label="Sales value by month">
                <line x1="0" x2="{{ $w }}" y1="{{ $h }}" y2="{{ $h }}" stroke="currentColor" stroke-opacity=".15"/>
                @foreach ($months as $i => $m)
                    @php
                        $x = $i * $slot + 12;
                        $bh = ($h - 26) * $m['value'] / $maxMonth;
                    @endphp
                    <rect x="{{ $x }}" y="{{ $h - $bh }}" width="{{ $bw }}" height="{{ max($bh, $m['value'] > 0 ? 2 : 0) }}" rx="5" fill="{{ $palette[$i] }}"><title>{{ $m['label'] }}: ${{ number_format($m['value']) }}, {{ $m['cars'] }} cars</title></rect>
                    <text x="{{ $x + $bw / 2 }}" y="{{ $h - $bh - 6 }}" font-size="11" font-weight="700" text-anchor="middle" fill="currentColor">{{ $m['value'] >= 1000 ? '$'.round($m['value'] / 1000).'k' : '$'.number_format($m['value']) }}</text>
                    <text x="{{ $x + $bw / 2 }}" y="{{ $h + 16 }}" font-size="11" text-anchor="middle" fill="currentColor" fill-opacity=".65">{{ $m['label'] }} · {{ $m['cars'] }}</text>
                @endforeach
            </svg>
            <div class="crmd-legend" style="{{ $muted }}">Bars: value of cars sold. Under each month: number of cars.</div>
        </x-filament::section>

        {{-- Sources --}}
        <x-filament::section>
            <x-slot name="heading">Where enquiries come from (90 days)</x-slot>
            @forelse ($sources as $src)
                <div class="crmd-hbar">
                    <span>{{ $src['label'] }}</span>
                    <span class="t"><span class="f" style="display:block;width:{{ round($src['count'] * 100 / $maxSource) }}%;background:{{ $src['colour'] }}"></span></span>
                    <strong style="text-align:right">{{ $src['count'] }}</strong>
                </div>
            @empty
                <p style="{{ $muted }}">No enquiries in the last 90 days.</p>
            @endforelse
        </x-filament::section>

        {{-- Open Cases by state (donut) --}}
        <x-filament::section>
            <x-slot name="heading">Open Cases by status</x-slot>
            @php
                $r = 60; $c = 2 * M_PI * $r; $offset = 0;
            @endphp
            <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <svg viewBox="0 0 160 160" width="160" height="160" role="img" aria-label="Open Cases by status">
                    <circle cx="80" cy="80" r="{{ $r }}" fill="none" stroke="currentColor" stroke-opacity=".08" stroke-width="22"/>
                    @foreach ($states as $st)
                        @php
                            $len = $c * $st['count'] / $stateTotal;
                        @endphp
                        @if ($st['count'])
                            <circle cx="80" cy="80" r="{{ $r }}" fill="none" stroke="{{ $st['colour'] }}" stroke-width="22"
                                    stroke-dasharray="{{ $len }} {{ $c - $len }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 80 80)"><title>{{ $st['label'] }}: {{ $st['count'] }}</title></circle>
                        @endif
                        @php
                            $offset += $len;
                        @endphp
                    @endforeach
                    <text x="80" y="78" text-anchor="middle" font-size="26" font-weight="800" fill="currentColor">{{ $states->sum('count') }}</text>
                    <text x="80" y="96" text-anchor="middle" font-size="11" fill="currentColor" fill-opacity=".6">open</text>
                </svg>
                <div style="display:grid;gap:8px;font-size:14px">
                    @foreach ($states as $st)
                        <div><span class="crmd-pill" style="background:{{ $st['colour'] }}">{{ $st['count'] }}</span> {{ $st['label'] }}</div>
                    @endforeach
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Needs attention --}}
    <x-filament::section>
        <x-slot name="heading">Needs attention</x-slot>
        <x-slot name="description">Late replies, Cases without an owner and overdue follow-ups.</x-slot>
        @if ($d['attention']->isEmpty())
            <p style="{{ $muted }}">Nothing needs attention right now. 🎉</p>
        @else
            <table class="crmd-att">
                @foreach ($d['attention'] as $case)
                    @php
                        $isLate = $case->slaTimers->contains(fn ($t) => $t->breached_at !== null);
                        $overdue = $case->next_follow_up_at && $case->next_follow_up_at->isPast();
                    @endphp
                    <tr>
                        <td style="white-space:nowrap"><a href="{{ \App\Modules\Crm\Filament\Resources\Cases\CaseResource::getUrl('view', ['record' => $case]) }}" style="font-weight:700;color:#6366f1">{{ $case->reference }}</a></td>
                        <td>{{ $case->customer?->name ?? '—' }}<div style="{{ $muted }};font-size:12px">{{ $case->progressLabel() }}</div></td>
                        <td>{{ $case->owner?->name ?? 'Unassigned' }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            @if ($isLate)<span class="crmd-pill" style="background:#ef4444">Late</span>@endif
                            @if (! $case->owner_id)<span class="crmd-pill" style="background:#f59e0b">No owner</span>@endif
                            @if ($overdue)<span class="crmd-pill" style="background:#a855f7">Follow-up due</span>@endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
