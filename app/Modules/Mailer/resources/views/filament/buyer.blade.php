<x-filament-panels::page>
    @php
        /** @var \App\Modules\Mailer\Models\Buyer $buyer */
        $buyer = $this->record;
        $enquiries = $buyer->enquiries()->orderByDesc('received_at')->get();
        $tz = (string) config('mailer.display_timezone');
        $row = 'display:grid;grid-template-columns:150px minmax(0,1fr);gap:.5rem;padding:.35rem 0;border-bottom:1px solid rgba(127,127,127,.15);';
        $label = 'font-size:.8rem;opacity:.65;';
        $kinds = \App\Modules\Mailer\Models\BuyerEnquiry::KINDS;
    @endphp

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));align-items:start;">
        <x-filament::section>
            <x-slot name="heading">Buyer</x-slot>
            <div style="{{ $row }}"><span style="{{ $label }}">Email</span><span style="word-break:break-all;">{{ $buyer->email }}</span></div>
            <div style="{{ $row }}"><span style="{{ $label }}">Phone</span>
                <span>
                    {{ $buyer->phone_e164 ?: ($buyer->phone ?: '—') }}
                    @if ($buyer->whatsappUrl())
                        &nbsp;<a href="{{ $buyer->whatsappUrl() }}" target="_blank" rel="noopener" style="color:#16a34a;font-weight:600;">WhatsApp</a>
                    @endif
                    @if ($buyer->phone && $buyer->phone_e164 && $buyer->phone !== $buyer->phone_e164)
                        <br><span style="font-size:.8rem;opacity:.65;">as typed: {{ $buyer->phone }}</span>
                    @endif
                </span>
            </div>
            <div style="{{ $row }}"><span style="{{ $label }}">Country</span><span>{{ $buyer->country ?: '—' }}@if ($buyer->country_code) ({{ $buyer->country_code }})@endif</span></div>
            <div style="{{ $row }}"><span style="{{ $label }}">Port</span><span>{{ $buyer->port ?: '—' }}</span></div>
            <div style="{{ $row }}"><span style="{{ $label }}">Type</span><span>{{ \App\Modules\Mailer\Models\Buyer::TYPES[$buyer->buyer_type] ?? '—' }}</span></div>
            <div style="{{ $row }}"><span style="{{ $label }}">Source</span><span>{{ $buyer->sender?->label ?? '—' }}</span></div>
            <div style="{{ $row }}"><span style="{{ $label }}">Enquiries</span><span>{{ $buyer->enquiry_count }} · first {{ $buyer->first_enquiry_at?->timezone($tz)->format('j M Y') ?? '—' }} · last {{ $buyer->last_enquiry_at?->timezone($tz)->format('j M Y') ?? '—' }}</span></div>
            <div style="{{ $row }}border-bottom:0;"><span style="{{ $label }}">In Brevo</span><span>{{ $buyer->brevo_synced_at ? 'Details sent '.$buyer->brevo_synced_at->timezone($tz)->format('j M Y, H:i') : 'Not yet' }}</span></div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">What they asked about</x-slot>
            @php
                $makes = $enquiries->filter(fn ($e) => $e->make)->groupBy(fn ($e) => trim($e->make.' '.$e->model))->map->count()->sortDesc();
            @endphp
            @forelse ($makes as $vehicle => $n)
                <div style="{{ $row }}grid-template-columns:minmax(0,1fr) auto;"><span>{{ $vehicle }}</span><span style="opacity:.7;">{{ $n }}×</span></div>
            @empty
                <p style="margin:0;opacity:.7;">No vehicle details in their enquiries.</p>
            @endforelse
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Enquiries ({{ $enquiries->count() }})</x-slot>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;min-width:640px;">
                <thead>
                    <tr style="text-align:left;opacity:.7;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">
                        <th style="padding:.5rem;">Received</th><th style="padding:.5rem;">Kind</th><th style="padding:.5rem;">Vehicle</th><th style="padding:.5rem;">Drive</th>
                        <th style="padding:.5rem;">Budget</th><th style="padding:.5rem;">Destination</th><th style="padding:.5rem;">Other details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enquiries as $e)
                        <tr style="border-top:1px solid rgba(127,127,127,.15);vertical-align:top;">
                            <td style="padding:.5rem;white-space:nowrap;">{{ $e->received_at?->timezone($tz)->format('j M Y, H:i') }}</td>
                            <td style="padding:.5rem;">{{ $kinds[$e->kind] ?? $e->kind }}</td>
                            <td style="padding:.5rem;font-weight:600;">{{ $e->vehicleLabel() ?: '—' }}</td>
                            <td style="padding:.5rem;">{{ $e->drive ?? '—' }}</td>
                            <td style="padding:.5rem;">{{ $e->budget ?? '—' }}</td>
                            <td style="padding:.5rem;">{{ trim(($e->port ?? '').($e->country_code ? ' ('.$e->country_code.')' : '')) ?: '—' }}</td>
                            <td style="padding:.5rem;font-size:.8rem;">
                                @foreach (($e->details ?? []) as $k => $v)
                                    <span style="opacity:.65;">{{ str_replace('_', ' ', $k) }}:</span> {{ is_array($v) ? implode(', ', $v) : $v }}<br>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin:.75rem 0 0;font-size:.8rem;opacity:.65;">Only the details above are kept. The buyer's own message stays in the mailbox and is not stored here.</p>
    </x-filament::section>
</x-filament-panels::page>
