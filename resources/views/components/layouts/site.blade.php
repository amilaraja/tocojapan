<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Toco Japan') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-ink antialiased bg-surface-2 min-h-screen">
    <header class="bg-toco-navy text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <a href="/" class="inline-flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-md bg-toco-red text-white font-bold text-sm">TJ</span>
                <span class="font-extrabold tracking-tight text-lg">Toco Japan</span>
            </a>
            <nav class="hidden sm:flex items-center gap-6 text-sm">
                <a href="{{ route('vehicles.index') }}" class="hover:text-white/80">Vehicles</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="hover:text-white/80">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-white/80">Sign in</a>
                    <a href="{{ route('register') }}" class="bg-toco-red px-3 py-1.5 rounded font-semibold text-xs uppercase tracking-widest">Register</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <footer class="bg-toco-navy-deep text-white/80 mt-12 py-6 text-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            &copy; {{ date('Y') }} Toco Japan — Japanese auto exporter.
        </div>
    </footer>
</body>
</html>
