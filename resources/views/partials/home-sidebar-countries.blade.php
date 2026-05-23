@php
    $popularCountries = [
        ['popular-usa', 'United States', 'us'],
        ['popular-uk', 'United Kingdom', 'sh'],
        ['popular-zambia', 'Zambia', 'za'],
        ['popular-tanzania', 'Tanzania', 'tz'],
        ['popular-uganda', 'Uganda', 'ug'],
        ['popular-mozambique', 'Mozambique', 'mz'],
        ['popular-drcongo', 'D.R. Congo', 'cd'],
        ['popular-zimbabwe', 'Zimbabwe', 'zw'],
        ['popular-bangladesh', 'Bangladesh', 'bd'],
        ['popular-pakistan', 'Pakistan', 'pk'],
        ['popular-mongolia', 'Mongolia', 'mn'],
        ['popular-sri-lanka', 'Sri Lanka', 'lk'],
        ['popular-canada', 'Canada', 'ca'],
        ['popular-new-zealand', 'New Zealand', 'nz'],
        ['popular-australia', 'Australia', 'au'],
    ];
@endphp
<aside class="bg-white border border-line rounded-sm overflow-hidden">
    @include('partials.home-sidebar-header', ['kicker' => 'Popular by', 'title' => 'Country'])
    <ul class="text-[13px]">
        @foreach ($popularCountries as [$slug, $label, $cc])
            <li>
                <a href="{{ url('/'.$slug) }}" class="flex items-center gap-2.5 px-3 py-2 hover:bg-toco-silver-2 border-b border-line/60 last:border-b-0">
                    <img src="/legacy/uploads/2023/11/{{ $cc }}.svg" alt="" width="22" height="16" class="shrink-0 rounded-[2px] border border-line" loading="lazy">
                    <span class="flex-1 font-semibold">{{ $label }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</aside>
