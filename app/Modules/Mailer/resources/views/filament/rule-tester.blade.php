<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-information-circle" icon-color="info">
        <x-slot name="heading">Test only, nothing is saved</x-slot>
        <x-slot name="description">Nothing is sent to Brevo and nothing is added to the logs.</x-slot>
    </x-filament::section>

    <form wire:submit="test">
        {{ $this->form }}
        <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
            <x-filament::button type="submit" icon="heroicon-o-beaker">Test</x-filament::button>
        </div>
    </form>

    @if ($result)
        <x-filament::section>
            <x-slot name="heading">Result</x-slot>
            @if ($result['fromMatches'] === false)
                <p style="margin:0 0 1rem 0;color:#A3000A;"><strong>Note:</strong> this message is from {{ $result['from'] }}, which does not match the chosen sender. The importer would not read it.</p>
            @endif

            @if ($result['addresses'] === [])
                <p style="margin:0;">No email addresses found.</p>
            @else
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr><th style="text-align:left;padding:.5rem;border-bottom:1px solid rgba(0,0,0,.1);">Address</th><th style="text-align:left;padding:.5rem;border-bottom:1px solid rgba(0,0,0,.1);">Result</th></tr></thead>
                    <tbody>
                    @foreach ($result['addresses'] as $a)
                        <tr>
                            <td style="padding:.5rem;border-bottom:1px solid rgba(0,0,0,.05);">{{ $a['email'] }}</td>
                            <td style="padding:.5rem;border-bottom:1px solid rgba(0,0,0,.05);">
                                <x-filament::badge :color="$a['keep'] ? 'success' : 'gray'" style="display:inline-flex;">{{ $a['keep'] ? 'Keep' : 'Skip' }}</x-filament::badge>
                                {{ $a['reason'] }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <h3 style="margin:1.25rem 0 .5rem 0;font-weight:600;">Details found</h3>
            @forelse ($result['fields'] as $label => $value)
                <p style="margin:0;">{{ $label }}: <strong>{{ $value }}</strong></p>
            @empty
                <p style="margin:0;">None. Add field rules to the sender to pick up names, countries or phone numbers.</p>
            @endforelse
        </x-filament::section>
    @endif
</x-filament-panels::page>
