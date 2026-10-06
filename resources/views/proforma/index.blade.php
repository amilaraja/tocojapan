<x-layouts.account title="My Proforma Invoices — Toco Japan" heading="My Proforma Invoices" active="proforma">
    <div>

        @if (session('status'))
            <div class="mb-6 bg-white border border-line border-l-4 border-l-toco-red rounded-sm p-4 text-sm flex flex-wrap items-center justify-between gap-3">
                <span>{{ session('status') }}</span>
                @if (session('download'))
                    <a href="{{ session('download') }}" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2 rounded-sm">Download PDF</a>
                    {{-- Start the download straight away. --}}
                    <iframe src="{{ session('download') }}" class="hidden" title="download"></iframe>
                @endif
            </div>
        @endif

        @if ($invoices->isEmpty())
            <p class="text-ink-soft">No proforma invoices yet. Open a vehicle, choose a destination that accepts LC and click "LC proforma invoice".</p>
        @else
            <div class="bg-white border border-line rounded-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-toco-silver-2 text-left text-[11px] uppercase tracking-widest text-ink-soft">
                        <tr><th class="px-4 py-2">Invoice</th><th class="px-4 py-2">Vehicle</th><th class="px-4 py-2">Destination</th><th class="px-4 py-2 text-right">Total CIF</th><th class="px-4 py-2">Valid until</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $inv)
                            <tr class="border-t border-line">
                                <td class="px-4 py-3 font-semibold text-toco-navy">{{ $inv->invoice_no }}</td>
                                <td class="px-4 py-3">{{ $inv->snapshot['title'] ?? '' }}</td>
                                <td class="px-4 py-3">{{ $inv->snapshot['port'] ?? '' }} / {{ $inv->snapshot['country'] ?? '' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">US$ {{ number_format((float) $inv->total_cif) }}</td>
                                <td class="px-4 py-3 {{ $inv->isExpired() ? 'text-ink-soft line-through' : '' }}">{{ $inv->expires_on->format('j M Y') }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('proforma.download', $inv) }}" class="text-toco-red font-bold uppercase tracking-widest text-xs hover:underline">PDF</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $invoices->links() }}</div>
        @endif
    </div>
</x-layouts.account>
