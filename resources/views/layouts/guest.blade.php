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
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-surface-2">
            <div>
                <a href="/" class="inline-flex items-center gap-2 text-toco-navy">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-toco-red text-white font-bold text-sm">TJ</span>
                    <span class="font-extrabold tracking-tight text-lg">Toco Japan</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white shadow-sm border border-line border-t-4 border-t-toco-red overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
