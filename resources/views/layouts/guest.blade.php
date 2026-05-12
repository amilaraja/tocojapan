<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Toco Japan') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="font-sans text-ink antialiased">
        <div
            class="min-h-screen flex flex-col items-center justify-center px-4 py-10 relative overflow-hidden"
            style="background:
                radial-gradient(ellipse 80% 60% at top, rgba(227, 6, 19, 0.10), transparent 60%),
                linear-gradient(135deg, #10143A 0%, #1F2356 50%, #10143A 100%);"
        >
            @php
                $headerLogo = app(\App\Settings\GeneralSettings::class)->header_logo ?? null;
            @endphp
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 text-white">
                @if ($headerLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($headerLogo) }}" alt="{{ config('app.name', 'Toco Japan') }}" class="h-11 w-auto bg-white/95 rounded-sm px-3 py-1.5">
                @else
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-sm bg-toco-red text-white font-bold text-sm font-mono">TJ</span>
                    <span class="font-extrabold tracking-tight text-xl">Toco Japan</span>
                @endif
            </a>

            @php
                [$heading, $subheading] = match (request()->route()?->getName()) {
                    'register'         => ['Create your account', 'Save searches · request quotes · place orders'],
                    'password.request' => ['Forgot your password?', 'We\'ll email you a reset link'],
                    'password.reset'   => ['Reset your password', 'Pick a new password to log in with'],
                    'password.confirm' => ['Confirm your password', 'Verify it\'s you before continuing'],
                    'verification.notice' => ['Verify your email', 'Check your inbox for the confirmation link'],
                    default            => ['Sign in to your account', 'Toco Japan customer portal'],
                };
            @endphp
            <div class="w-full sm:max-w-md mt-6 bg-white/97 backdrop-blur shadow-2xl border-t-4 border-t-toco-red overflow-hidden rounded-sm">
                <div class="px-6 pt-6 pb-2 text-center">
                    <h1 class="text-lg font-extrabold text-toco-navy">{{ $heading }}</h1>
                    <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mt-1">{{ $subheading }}</p>
                </div>
                <div class="px-6 pb-6 pt-2">
                    {{ $slot }}
                </div>
            </div>

            <p class="text-white/55 text-[11px] mt-6">
                © {{ date('Y') }} {{ config('app.name', 'Toco Japan') }} · <a href="{{ url('/') }}" class="hover:text-white">Back to site</a>
            </p>
        </div>
        @stack('scripts')
    </body>
</html>
