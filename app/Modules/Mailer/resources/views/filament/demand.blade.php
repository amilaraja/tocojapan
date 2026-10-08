<x-filament-panels::page>
    @php
        $r = $this->report();
        $bars = function ($rows, $title, $note = null) {
            return compact('rows', 'title', 'note');
        };
        $blocks = [
            $bars($r['models'], 'Most asked-for vehicles', 'Make and model'),
            $bars($r['makes'], 'Makes'),
            $bars($r['countries'], 'Countries'),
            $bars($r['months'], 'Enquiries per month'),
            $bars($r['kinds'], 'Kind of enquiry'),
            $bars($r['types'], 'Buyer type'),
            $bars($r['drive'], 'Steering asked for'),
        ];
        $field = 'display:flex;flex-direction:column;gap:.25rem;font-size:.8rem;';
        $select = 'min-width:180px;border-radius:.5rem;border:1px solid rgba(127,127,127,.35);background:transparent;padding:.4rem .6rem;font-size:.875rem;';
    @endphp

    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
        <label style="{{ $field }}">Period
            <select wire:model.live="days" style="{{ $select }}">
                <option value="30">Last 30 days</option>
                <option value="90">Last 3 months</option>
                <option value="180">Last 6 months</option>
                <option value="365">Last 12 months</option>
                <option value="all">All time</option>
            </select>
        </label>
        <label style="{{ $field }}">Country
            <select wire:model.live="country" style="{{ $select }}">
                <option value="">All countries</option>
                @foreach ($this->countries() as $code => $name)
                    <option value="{{ $code }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <p style="margin:0 0 .4rem;font-size:.9rem;"><strong>{{ number_format($r['total']) }}</strong> enquiries from <strong>{{ number_format($r['buyers']) }}</strong> buyers</p>
    </div>

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));align-items:start;">
        @foreach ($blocks as $block)
            <x-filament::section>
                <x-slot name="heading">{{ $block['title'] }}</x-slot>
                @php($max = max(1, (int) ($block['rows']->max('n') ?? 1)))
                @forelse ($block['rows'] as $row)
                    <div style="display:grid;grid-template-columns:minmax(0,1fr) 64px;gap:.5rem;align-items:center;padding:.2rem 0;font-size:.875rem;">
                        <div style="min-width:0;">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $row->label }}</div>
                            <div style="height:6px;border-radius:3px;background:rgba(127,127,127,.15);margin-top:3px;">
                                <div style="height:6px;border-radius:3px;background:#E30613;width:{{ round($row->n / $max * 100, 1) }}%;"></div>
                            </div>
                        </div>
                        <div style="text-align:right;font-variant-numeric:tabular-nums;" title="{{ $row->buyers }} buyers">{{ number_format($row->n) }}</div>
                    </div>
                @empty
                    <p style="margin:0;opacity:.7;">No enquiries in this period.</p>
                @endforelse
            </x-filament::section>
        @endforeach
    </div>
    <p style="margin:0;font-size:.8rem;opacity:.65;">Numbers are enquiries; hover a number to see how many different buyers sent them.</p>
</x-filament-panels::page>
