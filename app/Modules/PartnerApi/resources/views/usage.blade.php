<x-filament-panels::page>
    @php($partnerModel = $this->selected())
    <div style="max-width: 420px">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="partner">
                @foreach ($this->partnerOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    @if (! $partnerModel)
        <x-filament::section>No partners yet. Add one under Partner Network, Partners.</x-filament::section>
    @else
        @php($s = $this->stats())
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px">
            @foreach ([
                ['Calls today', number_format($s['calls']).' of '.number_format($s['calls_limit']), $s['calls_pct']],
                ['Photos today', number_format($s['photos']).' of '.number_format($s['photos_limit']), $s['photos_pct']],
                ['Errors today', number_format($s['errors']), null],
                ['Last successful sync', $s['last_sync'] ? $s['last_sync']->diffForHumans() : 'Never', null],
            ] as [$label, $value, $pct])
                <x-filament::section compact>
                    <div style="font-size:12px;opacity:.7">{{ $label }}</div>
                    <div style="font-size:20px;font-weight:600;margin-top:2px">{{ $value }}</div>
                    @if ($pct !== null)
                        <div style="height:6px;background:rgba(127,127,127,.2);border-radius:3px;margin-top:8px" title="{{ $pct }}%">
                            <div style="height:6px;border-radius:3px;width:{{ $pct }}%;background:{{ $pct >= 80 ? '#d97706' : '#16a34a' }}"></div>
                        </div>
                    @endif
                </x-filament::section>
            @endforeach
            @if ($s['holds'] !== null)
                <x-filament::section compact>
                    <div style="font-size:12px;opacity:.7">Active holds</div>
                    <div style="font-size:20px;font-weight:600;margin-top:2px">{{ $s['holds'] }} of {{ $s['max_holds'] }}</div>
                </x-filament::section>
            @endif
        </div>

        @php($days = $s['days'])
        @php($max = max(1, collect($days)->max(fn ($d) => max($d['requests'], $d['images']))))
        @php($w = 720)
        @php($h = 160)
        @php($x = fn ($i) => round(8 + $i * (($w - 16) / max(1, count($days) - 1)), 1))
        @php($y = fn ($v) => round($h - 20 - ($v / $max) * ($h - 36), 1))
        <x-filament::section heading="Last 30 days" description="Requests per day (red) and photo downloads per day (blue), Japan time.">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%;height:auto" role="img" aria-label="Requests and photo downloads per day, last 30 days">
                <line x1="8" x2="{{ $w - 8 }}" y1="{{ $h - 20 }}" y2="{{ $h - 20 }}" stroke="currentColor" stroke-opacity=".2"/>
                <text x="8" y="12" font-size="11" fill="currentColor" fill-opacity=".6">{{ number_format($max) }}</text>
                <polyline fill="none" stroke="#E30613" stroke-width="2"
                    points="{{ collect($days)->map(fn ($d, $i) => $x($i).','.$y($d['requests']))->implode(' ') }}"/>
                <polyline fill="none" stroke="#1F2356" stroke-width="2" stroke-dasharray="5 3"
                    points="{{ collect($days)->map(fn ($d, $i) => $x($i).','.$y($d['images']))->implode(' ') }}"/>
                <text x="8" y="{{ $h - 4 }}" font-size="11" fill="currentColor" fill-opacity=".6">{{ \Illuminate\Support\Carbon::parse($days[0]['date'])->format('j M') }}</text>
                <text x="{{ $w - 8 }}" y="{{ $h - 4 }}" font-size="11" text-anchor="end" fill="currentColor" fill-opacity=".6">Today</text>
            </svg>
        </x-filament::section>

        @php($hook = $this->webhookHealth())
        <x-filament::section heading="Change notifications">
            @if (! $hook)
                <p style="opacity:.7">This partner has no notification address. It checks for changes on a schedule.</p>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px">
                    <div><div style="font-size:12px;opacity:.7">Last delivered</div>
                        <div>{{ $hook['last_delivered'] ? \Illuminate\Support\Carbon::parse($hook['last_delivered'])->diffForHumans() : 'Never' }}</div></div>
                    <div><div style="font-size:12px;opacity:.7">Waiting to retry</div><div>{{ $hook['failing'] }}</div></div>
                    <div><div style="font-size:12px;opacity:.7">Given up (7 days)</div><div>{{ $hook['gave_up'] }}</div></div>
                </div>
                @if ($hook['failing_over_hour'])
                    <div style="margin-top:12px;padding:10px 12px;border-radius:6px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca">
                        Failing for over an hour. Last error: {{ $hook['last_error'] ?? 'unknown' }}
                    </div>
                @endif
            @endif
        </x-filament::section>

        {{ $this->table }}
    @endif
</x-filament-panels::page>
