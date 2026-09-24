@php
    $livewire = $this;
    $record = $livewire->getRecord();
    $issues = $livewire->issues ?? [];
    $count = count($livewire->data['vehicles'] ?? []);
    $min = (int) config('mailer.campaign.min_vehicles', 2);
    $notice = fn (string $bg, string $fg) => "padding:.75rem 1rem;border-radius:.5rem;background:{$bg};color:{$fg};margin:0 0 .5rem 0;";
@endphp
<div role="status" aria-live="polite">
    @foreach ($issues as $issue)
        @if ($issue['blocking'])
            <p style="{{ $notice('#FDECEC', '#A3000A') }}"><strong>Blocks push:</strong> {{ $issue['message'] }}</p>
        @else
            <p style="{{ $notice('#FFF6E0', '#6B4A00') }}">{{ $issue['message'] }}</p>
        @endif
    @endforeach

    @if ($count < $min)
        <p style="{{ $notice('#F4F4F6', '#3F3F46') }}">Push is not available yet: add at least {{ $min }} vehicles (now {{ $count }}).</p>
    @endif

    @if ($record->brevo_campaign_id)
        <p style="{{ $notice('#E8F1FF', '#12345E') }}">
            <strong>{{ \App\Modules\Mailer\Models\Campaign::STATUS_LABELS[$record->status] ?? $record->status }}</strong>
            &middot; Brevo campaign #{{ $record->brevo_campaign_id }}
            @if ($record->pushed_at) &middot; last pushed {{ $record->pushed_at->timezone(config('mailer.display_timezone'))->format('j M Y, H:i') }} @endif
            <br>Sending happens in Brevo only: send a test, check it, then send.
            @if ($record->status === \App\Modules\Mailer\Models\Campaign::STATUS_CHANGED) Push again to update the Brevo draft. @endif
        </p>
    @endif
</div>
