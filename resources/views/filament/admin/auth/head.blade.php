<style>
    /* Themed background for the admin auth pages */
    .fi-simple-layout {
        background:
            radial-gradient(ellipse 80% 60% at top, rgba(227, 6, 19, 0.08), transparent 60%),
            linear-gradient(135deg, #10143A 0%, #1F2356 50%, #10143A 100%) !important;
        min-height: 100vh;
    }
    .fi-simple-main-ctn { position: relative; z-index: 1; }
    .fi-simple-main {
        background: rgba(255, 255, 255, 0.97) !important;
        backdrop-filter: blur(6px);
        border-top: 4px solid #E30613;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5);
    }
    .fi-simple-header { display: none !important; } /* hide Filament's stock heading; we render our own */
    .toco-auth-brand { text-align: center; }
    .toco-auth-brand img { display: inline-block; max-height: 56px; width: auto; margin: 0 auto 0.75rem; }
    .toco-auth-brand h1 { color: #1F2356; font-weight: 800; font-size: 1.25rem; letter-spacing: -0.01em; }
    .toco-auth-brand p { color: #6B7280; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.15em; font-family: ui-monospace, monospace; margin-top: 0.25rem; }
    .cf-turnstile { margin-top: 0.75rem; display: flex; justify-content: center; }
</style>
@if (! empty(config('services.turnstile.site_key')))
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
