<x-filament-panels::page>
    @if ($failures >= 3)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">The importer has failed {{ $failures }} times in a row</x-slot>
            <x-slot name="description">{{ $last?->error }} Mailer Admins have been emailed. Check the run log.</x-slot>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Status</x-slot>
        <dl style="display:grid;grid-template-columns:max-content 1fr;gap:.5rem 1.5rem;margin:0;">
            <dt><strong>Mailbox</strong></dt>
            <dd style="margin:0;">
                @if ($mailboxReady)
                    <x-filament::badge color="success" style="display:inline-flex;">Connected</x-filament::badge> {{ $mailbox }}
                @else
                    <x-filament::badge color="warning" style="display:inline-flex;">Not connected</x-filament::badge> Add the mailbox and Google key file in Mailer settings.
                @endif
            </dd>
            <dt><strong>Brevo</strong></dt>
            <dd style="margin:0;">
                @if ($brevoReady)
                    <x-filament::badge color="success" style="display:inline-flex;">Key saved</x-filament::badge>
                @else
                    <x-filament::badge color="warning" style="display:inline-flex;">Not connected</x-filament::badge> Add the Brevo key in Mailer settings.
                @endif
            </dd>
            <dt><strong>Approved senders</strong></dt>
            <dd style="margin:0;">{{ $activeSenders }} active</dd>
            <dt><strong>Checks every</strong></dt>
            <dd style="margin:0;">{{ $interval }} minutes</dd>
            <dt><strong>Last run</strong></dt>
            <dd style="margin:0;">
                @if ($last)
                    {{ $last->started_at->timezone($tz)->format('j M Y, H:i') }} &middot;
                    {{ ['success' => 'OK', 'failed' => 'Failed', 'running' => 'Running'][$last->status] ?? $last->status }}
                    &middot; {{ $last->scanned }} messages, {{ $last->created }} added, {{ $last->updated }} updated
                @else
                    Not run yet
                @endif
            </dd>
            <dt><strong>Next run</strong></dt>
            <dd style="margin:0;">
                @if ($running)
                    Running now
                @elseif ($next)
                    About {{ $next->format('H:i') }} (Tokyo time)
                @else
                    Waiting for the mailbox connection
                @endif
            </dd>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
