@php
    $id = (int) $getState();
    $s = $snapshots[$id] ?? null;
    $issue = collect($issues)->firstWhere('vehicle_id', $id);
    $v = $s ? \App\Modules\Mailer\Domain\Vehicles\VehicleDTO::fromArray($s) : null;
@endphp
@if ($v)
    <div style="display:flex;gap:1rem;align-items:center;{{ ($issue['blocking'] ?? false) ? 'outline:2px solid #E30613;outline-offset:4px;border-radius:4px;' : '' }}">
        <img src="{{ $v->photoUrl ?: asset('storage/'.\App\Modules\Mailer\Domain\Vehicles\EmailImageService::PLACEHOLDER) }}" alt="" width="96" height="55" style="width:96px;height:55px;object-fit:cover;border:1px solid #E4E4E8;flex:none;">
        <div style="min-width:0;flex:1;">
            <div style="font-weight:600;">{{ $v->title }}</div>
            <div style="font-size:.875rem;opacity:.8;">
                <span style="font-family:'Courier New',monospace;color:#B3000D;font-weight:700;">#{{ $v->stockRef }}</span>
                &middot; {{ $v->metaLine() }}
                @if ($v->badgeLabel()) &middot; <strong>{{ $v->badgeLabel() }}</strong> @endif
            </div>
        </div>
        <div style="text-align:right;flex:none;">
            @if ($v->previousPrice !== null && $v->priceFob !== null)<div style="font-size:.8rem;text-decoration:line-through;opacity:.7;">${{ number_format($v->previousPrice) }}</div>@endif
            <div style="font-weight:700;color:#B3000D;">{{ \App\Modules\Mailer\Domain\Campaigns\VehicleRecheck::money($v->priceFob) }} FOB</div>
        </div>
    </div>
    @if ($issue)
        <p style="margin:.5rem 0 0 0;font-size:.875rem;color:{{ $issue['blocking'] ? '#A3000A' : '#6B4A00' }};">{{ $issue['message'] }}</p>
    @endif
@else
    <p style="margin:0;">Vehicle #{{ $id }}</p>
@endif
