<x-filament-panels::page>
    <div @if (($cursor['status'] ?? null) === 'running') wire:poll.5s @endif>
        <x-filament::section>
            <x-slot name="heading">Progress</x-slot>
            @if (! $cursor)
                <p style="margin:0;">No backfill has been run. Press <strong>Start backfill</strong> and choose a start date.</p>
            @else
                @php
                    $label = ['running' => 'In progress', 'paused' => 'Paused', 'done' => 'Finished'][$cursor['status']] ?? $cursor['status'];
                    $color = ['running' => 'info', 'paused' => 'warning', 'done' => 'success'][$cursor['status']] ?? 'gray';
                @endphp
                <p style="margin:0 0 .75rem 0;"><x-filament::badge :color="$color" style="display:inline-flex;">{{ $label }}</x-filament::badge>
                    Messages since {{ \Carbon\Carbon::parse($cursor['from'])->format('j M Y') }}</p>
                <p style="margin:0;">{{ $cursor['batches_done'] ?? 0 }} batches done &middot; {{ number_format($cursor['messages_seen'] ?? 0) }} messages looked at</p>
                @if (($cursor['status'] ?? null) === 'running')
                    <div role="progressbar" aria-label="Backfill running" style="margin-top:1rem;height:6px;background:rgba(0,0,0,.08);border-radius:3px;overflow:hidden;">
                        <div style="width:35%;height:100%;background:#E30613;animation:mailer-indeterminate 1.4s ease-in-out infinite;"></div>
                    </div>
                    <style>@keyframes mailer-indeterminate{0%{transform:translateX(-100%)}100%{transform:translateX(300%)}}</style>
                @endif
                <p style="margin:.75rem 0 0 0;font-size:.875rem;opacity:.75;">Details of each batch are in the run log.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
