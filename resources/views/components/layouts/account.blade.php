@props([
    'title' => 'My Account — Toco Japan',
    'heading' => 'My Account',
    'active' => 'dashboard',
])

@php
    $accountNav = [
        ['dashboard', 'Dashboard', route('dashboard'), 'M4 13h6V4H4zM14 9h6V4h-6zM14 20h6v-9h-6zM4 20h6v-7H4z'],
        ['orders', 'My Orders', route('orders.index'), 'M3 7l9-4 9 4-9 4-9-4zm0 0v10l9 4 9-4V7M12 11v10'],
        ['favorites', 'Wishlist', route('favorites.index'), 'M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.5-7 10-7 10z'],
        ['quotes', 'Quotes', route('quotes.index'), 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
        ['profile', 'Account Details', route('profile.edit'), 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z'],
    ];
    $accountUser = Auth::user();
@endphp

<x-layouts.site :title="$title">
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1280px] mx-auto px-6 py-8">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">My Toco account</p>
            <h1 class="text-2xl md:text-3xl font-extrabold mt-1">{{ $heading }}</h1>
        </div>
    </section>

    <div class="bg-surface py-8">
        <div class="max-w-[1280px] mx-auto px-6 grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-6 items-start">
            {{-- Account side navigation --}}
            <aside class="bg-white border border-line rounded-sm overflow-hidden lg:sticky lg:top-24">
                <div class="flex items-center gap-3 p-4 border-b border-line">
                    <span class="w-11 h-11 rounded-full overflow-hidden bg-toco-silver-2 border border-line grid place-items-center shrink-0">
                        @if ($accountUser?->avatarUrl())
                            <img src="{{ $accountUser->avatarUrl() }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-ink-soft"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-toco-navy text-sm truncate">{{ $accountUser?->name }}</p>
                        <p class="text-[11px] text-ink-soft truncate">{{ $accountUser?->email }}</p>
                    </div>
                </div>
                <nav class="py-1.5">
                    @foreach ($accountNav as [$key, $label, $url, $icon])
                        <a href="{{ $url }}"
                            class="flex items-center gap-3 px-4 py-2.5 text-[13px] font-semibold border-l-[3px] transition
                                {{ $active === $key
                                    ? 'border-toco-red text-toco-red bg-toco-silver-2'
                                    : 'border-transparent text-ink hover:text-toco-red hover:bg-toco-silver-2' }}">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><path d="{{ $icon }}"/></svg>
                            {{ $label }}
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-line mt-1.5 pt-1.5">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-[13px] font-semibold border-l-[3px] border-transparent text-ink hover:text-toco-red hover:bg-toco-silver-2 transition">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                            Log Out
                        </button>
                    </form>
                </nav>
            </aside>

            {{-- Page content --}}
            <div class="min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.site>
