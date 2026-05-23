@php
    // Shared sidebar header — navy bar + red diagonal accent (matches the
    // chevron-style heading in the v6 home-page mock).
    $kicker = $kicker ?? null;
    $title = $title ?? '';
@endphp
<div class="relative bg-toco-navy text-white px-3 py-2 overflow-hidden">
    {{-- red diagonal accent on the right --}}
    <div class="absolute top-0 right-0 h-full w-12 bg-toco-red"
         style="clip-path: polygon(35% 0, 100% 0, 100% 100%, 0 100%);" aria-hidden="true"></div>
    <div class="relative">
        @if ($kicker)
            <p class="font-mono text-[10px] uppercase tracking-widest text-white/70">{{ $kicker }}</p>
        @endif
        <h3 class="font-bold text-white text-[15px] leading-tight">{{ $title }}</h3>
    </div>
</div>
