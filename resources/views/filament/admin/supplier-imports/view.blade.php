<x-filament-panels::page>
    @php
        /** @var \App\Models\SupplierImport $import */
        $import = $this->record->refresh();
        $c = $import->stat('counts', []);
        $a = $import->stat('applied', []);
        $running = $import->isRunning();
        $total = max(1, (int) $import->valid_rows);
        $pct = match ($import->status) {
            'staging' => null,
            'previewing', 'applying' => (int) round($import->processed_rows / $total * 100),
            'completed', 'previewed' => 100,
            default => null,
        };
        $n = fn ($v) => number_format((int) $v);
        $money = fn ($v) => $v === null ? 'On request' : '$'.number_format((float) $v);
        $card = 'rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
        $label = 'text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
    @endphp

    <div @if ($running) wire:poll.3s @endif class="space-y-6">
        {{-- Status --}}
        <div class="{{ $card }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="{{ $label }}">{{ $import->supplier->name }} · {{ $import->mode === 'full' ? 'Complete stock file' : 'Partial file' }}</p>
                    <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $import->original_name }}</p>
                    <p class="mt-1 text-sm text-gray-500">
                        Uploaded {{ $import->created_at->format('Y-m-d H:i') }}{{ $import->user ? ' by '.$import->user->name : ' from the command line' }}
                        @if ($import->approved_at) · approved {{ $import->approved_at->format('Y-m-d H:i') }} @endif
                        @if ($import->finished_at) · finished {{ $import->finished_at->format('Y-m-d H:i') }} @endif
                    </p>
                </div>
                <div class="text-right">
                    <x-filament::badge :color="match ($import->status) { 'completed' => 'success', 'previewed' => 'warning', 'failed' => 'danger', 'cancelled' => 'gray', default => 'info' }">
                        {{ $import->statusLabel() }}
                    </x-filament::badge>
                    @if ($running)
                        <p class="mt-2 text-sm text-gray-500">
                            @if ($import->status === 'staging')
                                Reading file… {{ $n($import->total_rows) }} lines so far
                            @else
                                {{ $n($import->processed_rows) }} / {{ $n($import->valid_rows) }} vehicles
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            @if ($pct !== null && $running)
                <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-2 rounded-full bg-primary-500 transition-all" style="width: {{ $pct }}%"></div>
                </div>
            @endif

            @if ($import->status === 'previewed')
                <div class="mt-4 rounded-lg bg-warning-50 p-4 text-sm text-warning-800 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/20">
                    Nothing has changed on the website yet. Check the summary below, then press <strong>Approve &amp; apply changes</strong> — or discard the import.
                    @if ($import->needsDelistConfirmation())
                        <p class="mt-2 font-semibold text-danger-700 dark:text-danger-400">
                            Warning: this file would delist {{ $n($import->stat('to_delist')) }} of {{ $n($import->stat('live_before')) }} live vehicles
                            (more than {{ $import->supplier->setting('delist_guard_percent') }}%). Make sure the file is complete and not cut off.
                        </p>
                    @endif
                </div>
            @endif

            @if ($import->status === 'failed')
                <div class="mt-4 rounded-lg bg-danger-50 p-4 text-sm text-danger-700 ring-1 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-300">
                    {{ $import->message ?: 'The import stopped with an error.' }}
                </div>
            @endif
        </div>

        {{-- Numbers --}}
        @if (! in_array($import->status, ['queued', 'staging'], true))
            @php
                $tiles = [
                    ['New vehicles', $a['new'] ?? $c['new'] ?? 0, 'success'],
                    ['Updated', $a['update'] ?? $c['update'] ?? 0, 'info'],
                    ['Price up', $c['price_up'] ?? 0, 'gray'],
                    ['Price down', $c['price_down'] ?? 0, 'gray'],
                    ['Unchanged', $a['unchanged'] ?? $c['unchanged'] ?? 0, 'gray'],
                    ['Back in stock', $a['relist'] ?? $c['relist'] ?? 0, 'success'],
                    [$import->status === 'completed' ? 'Delisted' : 'Will be delisted', $a['delisted'] ?? $import->stat('to_delist'), 'danger'],
                    ['Locked (kept as edited)', $a['locked'] ?? $c['locked'] ?? 0, 'gray'],
                    ['Rows with errors', $import->error_rows, $import->error_rows ? 'danger' : 'gray'],
                ];
            @endphp
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($tiles as [$t, $v, $color])
                    <div class="{{ $card }}">
                        <p class="{{ $label }}">{{ $t }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $n($v) }}</p>
                    </div>
                @endforeach
                <div class="{{ $card }}">
                    <p class="{{ $label }}">Live stock</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                        {{ $n($import->stat('live_before')) }}
                        @if ($import->stat('live_after', null) !== null) → {{ $n($import->stat('live_after')) }} @endif
                    </p>
                    @if ($import->stat('exchange_rate', null))
                        <p class="mt-1 text-xs text-gray-500">Rate {{ number_format((float) $import->stat('exchange_rate'), 2) }} {{ $import->supplier->setting('source_currency') }}/USD</p>
                    @endif
                    @if (is_array($import->stat('margin', null)))
                        <p class="mt-1 text-xs text-gray-500">Margin {{ \App\Filament\Admin\Resources\SupplierImports\SupplierImportResource::marginLabel($import->stat('margin')) }}{{ $import->stat('margin_override', null) ? ' (set for this import)' : '' }}</p>
                    @endif
                </div>
            </div>
            @if (($import->stat('makes_created') ?? 0) || ($import->stat('models_created') ?? 0) || $import->stat('duplicates'))
                <p class="text-sm text-gray-500">
                    {{ $n($import->stat('makes_created')) }} new makes and {{ $n($import->stat('models_created')) }} new models were added to the catalogue.
                    @if ($import->stat('duplicates')) {{ $n($import->stat('duplicates')) }} duplicate lines in the file were merged (last one wins). @endif
                </p>
            @endif
        @endif

        @if (in_array($import->status, ['previewed', 'applying', 'delisting', 'completed'], true))
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                {{-- Biggest price changes --}}
                @php($changes = $this->priceChanges())
                @if ($changes->isNotEmpty())
                    <div class="{{ $card }}">
                        <p class="{{ $label }} mb-3">Biggest price changes</p>
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-gray-500"><th class="py-1">Vehicle</th><th class="py-1 text-right">Was</th><th class="py-1 text-right">Now</th></tr></thead>
                            <tbody>
                                @foreach ($changes as $row)
                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                        <td class="py-1"><a class="text-primary-600 hover:underline" href="{{ route('vehicles.show', $row->slug) }}" target="_blank">{{ $row->stock_no }}</a> {{ \Illuminate\Support\Str::limit($row->title, 40) }}</td>
                                        <td class="py-1 text-right text-gray-500">{{ $money($row->old_price_fob) }}</td>
                                        <td class="py-1 text-right font-medium {{ $row->new_price_fob > $row->old_price_fob ? 'text-danger-600' : 'text-success-600' }}">{{ $money($row->new_price_fob) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- To be delisted --}}
                @php($delist = $this->delistSample())
                @if ($delist->isNotEmpty())
                    <div class="{{ $card }}">
                        <p class="{{ $label }} mb-3">Will be delisted (first {{ $delist->count() }} of {{ $n($import->stat('to_delist')) }})</p>
                        <table class="w-full text-sm">
                            <tbody>
                                @foreach ($delist as $row)
                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                        <td class="py-1"><a class="text-primary-600 hover:underline" href="{{ route('vehicles.show', $row->slug) }}" target="_blank">{{ $row->stock_no }}</a> {{ \Illuminate\Support\Str::limit($row->title, 45) }}</td>
                                        <td class="py-1 text-right text-gray-500">{{ $money($row->price_fob) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- New vehicles --}}
                @php($fresh = $import->status === 'previewed' ? $this->newSample() : collect())
                @if ($fresh->isNotEmpty())
                    <div class="{{ $card }}">
                        <p class="{{ $label }} mb-3">New vehicles (first {{ $fresh->count() }} of {{ $n($c['new'] ?? 0) }})</p>
                        <table class="w-full text-sm">
                            <tbody>
                                @foreach ($fresh as $row)
                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                        <td class="py-1 text-gray-500">{{ $import->supplier->stock_prefix }}-{{ $row->supplier_ref }}</td>
                                        <td class="py-1">{{ $row->title }}</td>
                                        <td class="py-1 text-right">{{ $money($row->new_price_fob) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        {{-- Row errors --}}
        @if (! empty($import->errors_sample))
            <div class="{{ $card }}">
                <p class="{{ $label }} mb-3">Lines skipped ({{ $n($import->error_rows) }}{{ $import->error_rows > count($import->errors_sample) ? ', first '.count($import->errors_sample).' shown' : '' }})</p>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="py-1">Line</th><th class="py-1">Vehicle ID</th><th class="py-1">Problem</th></tr></thead>
                    <tbody>
                        @foreach ($import->errors_sample as $e)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="py-1">{{ $e['line'] }}</td>
                                <td class="py-1">{{ $e['ref'] ?? '—' }}</td>
                                <td class="py-1">{{ $e['error'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
