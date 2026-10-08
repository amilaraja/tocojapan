<x-filament-panels::page>
    @php
        $field = 'display:flex;flex-direction:column;gap:.25rem;font-size:.8rem;';
        $input = 'border-radius:.5rem;border:1px solid rgba(127,127,127,.35);background:transparent;padding:.4rem .6rem;font-size:.875rem;';
        $tz = (string) config('mailer.display_timezone');
        $results = $this->results();
        $total = $this->total();
    @endphp

    <x-filament::section>
        <x-slot name="heading">Which vehicle?</x-slot>
        <x-slot name="description">Pick one of your vehicles, or type the make and model. Buyers who asked for something similar are listed below.</x-slot>

        <div style="position:relative;max-width:520px;margin-bottom:1rem;">
            <label style="{{ $field }}">Find a vehicle in stock (title or stock no.)
                <input type="search" wire:model.live.debounce.300ms="vehicleSearch" placeholder="e.g. Hiace or E02059" style="{{ $input }}" autocomplete="off">
            </label>
            @php($options = $this->vehicleOptions())
            @if ($options->isNotEmpty())
                <div style="position:absolute;z-index:10;left:0;right:0;margin-top:2px;border:1px solid rgba(127,127,127,.3);border-radius:.5rem;overflow:hidden;" class="fi-dropdown-panel">
                    @foreach ($options as $v)
                        <button type="button" wire:click="pickVehicle({{ $v->id }})" style="display:block;width:100%;text-align:left;padding:.5rem .75rem;font-size:.875rem;border-bottom:1px solid rgba(127,127,127,.12);">
                            <strong>{{ $v->stock_no }}</strong> {{ $v->title }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <label style="{{ $field }}">Make *
                <input list="mailer-makes" wire:model.live.debounce.400ms="make" style="{{ $input }}min-width:160px;" placeholder="Toyota">
                <datalist id="mailer-makes">@foreach ($this->topMakes() as $m)<option value="{{ $m }}">@endforeach</datalist>
            </label>
            <label style="{{ $field }}">Model contains
                <input wire:model.live.debounce.400ms="model" style="{{ $input }}min-width:160px;" placeholder="Hiace">
            </label>
            <label style="{{ $field }}">Year from
                <input type="number" wire:model.live.debounce.400ms="yearFrom" style="{{ $input }}width:100px;" min="1950" max="2030">
            </label>
            <label style="{{ $field }}">Year to
                <input type="number" wire:model.live.debounce.400ms="yearTo" style="{{ $input }}width:100px;" min="1950" max="2030">
            </label>
            <label style="{{ $field }}">Steering
                <select wire:model.live="drive" style="{{ $input }}">
                    <option value="">Any</option><option value="RHD">RHD</option><option value="LHD">LHD</option>
                </select>
            </label>
            <label style="{{ $field }}">Buyer country
                <select wire:model.live="country" style="{{ $input }}max-width:220px;">
                    <option value="">All countries</option>
                    @foreach ($this->countries() as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
                </select>
            </label>
            <label style="{{ $field }}">Asked within
                <select wire:model.live="months" style="{{ $input }}">
                    <option value="3">3 months</option><option value="6">6 months</option><option value="12">12 months</option><option value="24">24 months</option><option value="all">Any time</option>
                </select>
            </label>
            <label style="display:flex;gap:.4rem;align-items:center;font-size:.8rem;padding-bottom:.45rem;">
                <input type="checkbox" wire:model.live="includeNoYear"> Include enquiries without a year
            </label>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            @if (trim($make) === '')
                Matching buyers
            @else
                {{ number_format($total) }} matching {{ \Illuminate\Support\Str::plural('buyer', $total) }}
            @endif
        </x-slot>
        @if ($total > 0)
            <x-slot name="afterHeader">
                <x-filament::button wire:click="download" icon="heroicon-o-arrow-down-tray" color="gray" size="sm">Download CSV</x-filament::button>
            </x-slot>
        @endif

        @if (trim($make) === '')
            <p style="margin:0;opacity:.7;">Choose a vehicle or type a make to see matching buyers.</p>
        @elseif ($results->isEmpty())
            <p style="margin:0;opacity:.7;">No buyers asked for this. Try a wider year range, another period or fewer filters.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;min-width:640px;">
                    <thead>
                        <tr style="text-align:left;opacity:.7;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">
                            <th style="padding:.5rem;">Buyer</th><th style="padding:.5rem;">Country / port</th><th style="padding:.5rem;">Phone</th>
                            <th style="padding:.5rem;">Type</th><th style="padding:.5rem;text-align:right;">Matching enquiries</th><th style="padding:.5rem;">Last asked</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $b)
                            <tr style="border-top:1px solid rgba(127,127,127,.15);">
                                <td style="padding:.5rem;">
                                    <a href="{{ \App\Modules\Mailer\Filament\Resources\Buyers\BuyerResource::getUrl('view', ['record' => $b]) }}" style="font-weight:600;color:#E30613;">{{ $b->fullName() ?: $b->email }}</a><br>
                                    <span style="font-size:.8rem;opacity:.7;">{{ $b->email }}</span>
                                </td>
                                <td style="padding:.5rem;">{{ $b->country ?: '—' }}@if ($b->port)<br><span style="font-size:.8rem;opacity:.7;">{{ $b->port }}</span>@endif</td>
                                <td style="padding:.5rem;white-space:nowrap;">
                                    {{ $b->phone_e164 ?: '—' }}
                                    @if ($b->whatsappUrl()) <a href="{{ $b->whatsappUrl() }}" target="_blank" rel="noopener" style="color:#16a34a;font-weight:600;font-size:.8rem;">WhatsApp</a>@endif
                                </td>
                                <td style="padding:.5rem;">{{ \App\Modules\Mailer\Models\Buyer::TYPES[$b->buyer_type] ?? '—' }}</td>
                                <td style="padding:.5rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $b->matching_count }}</td>
                                <td style="padding:.5rem;white-space:nowrap;">{{ $b->last_match_at ? \Carbon\Carbon::parse($b->last_match_at)->timezone($tz)->format('j M Y') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($total > $results->count())
                <p style="margin:.75rem 0 0;font-size:.8rem;opacity:.7;">Showing the {{ $results->count() }} most recent. The CSV has all {{ number_format($total) }}.</p>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
