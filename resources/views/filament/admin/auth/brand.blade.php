@php
    $headerLogo = app(\App\Settings\GeneralSettings::class)->header_logo ?? null;
@endphp
<div class="toco-auth-brand">
    @if ($headerLogo)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($headerLogo) }}" alt="{{ config('app.name', 'Toco Japan') }}">
    @else
        <div style="display:inline-flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border-radius:.25rem;background:#E30613;color:white;font-weight:700;font-family:ui-monospace,monospace;">TJ</span>
            <span style="font-weight:800;color:#1F2356;font-size:1.125rem;">Toco Japan</span>
        </div>
    @endif
    <h1>Admin sign in</h1>
    <p>Toco Japan management console</p>
</div>
