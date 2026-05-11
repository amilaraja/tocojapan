@props(['theme' => 'light', 'size' => 'normal'])

@php
    $siteKey = config('services.turnstile.site_key');
@endphp

@if ($siteKey)
    <div {{ $attributes->merge(['class' => 'mt-4']) }}>
        <div
            class="cf-turnstile"
            data-sitekey="{{ $siteKey }}"
            data-theme="{{ $theme }}"
            data-size="{{ $size }}"
        ></div>
        @error('cf-turnstile-response')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @once
        @push('head')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce
@endif
