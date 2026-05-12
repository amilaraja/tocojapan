@php
    $siteKey = config('services.turnstile.site_key');
@endphp
@if ($siteKey)
    <div
        class="cf-turnstile"
        data-sitekey="{{ $siteKey }}"
        data-callback="tocoSetTurnstileToken"
        data-error-callback="tocoClearTurnstileToken"
        data-expired-callback="tocoClearTurnstileToken"
    ></div>
    @error('data.turnstile')
        <p style="text-align:center;color:#b91c1c;font-size:.75rem;margin-top:.5rem;">{{ $message }}</p>
    @enderror
    <script>
        // Push the Cloudflare-issued token onto the Livewire login component
        // so the overridden authenticate() method can validate it server-side.
        window.tocoSetTurnstileToken = function (token) {
            const root = document.querySelector('[wire\\:id]');
            if (root && window.Livewire) {
                const component = window.Livewire.find(root.getAttribute('wire:id'));
                if (component) component.set('turnstileToken', token, false);
            }
        };
        window.tocoClearTurnstileToken = function () {
            window.tocoSetTurnstileToken('');
        };
    </script>
@endif
