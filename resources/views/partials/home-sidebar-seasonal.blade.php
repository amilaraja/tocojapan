@php
    $sx = $content['seasonal'] ?? [];
    if (! ($sx['enabled'] ?? true)) {
        return;
    }
    $sxImg = $sx['sidebar_image'] ?? $sx['image'] ?? '/img/v5/seasonal-banner.jpg';
    if ($sxImg !== '' && ! str_starts_with($sxImg, '/') && ! str_starts_with($sxImg, 'http')) {
        $sxImg = '/storage/'.$sxImg;
    }
    $sxUrl = $sx['cta_url'] ?? route('vehicles.index');
    $sxAlt = $sx['tag'] ?? 'Seasonal promotion';
@endphp
<a href="{{ $sxUrl }}" aria-label="{{ $sxAlt }}" class="block overflow-hidden border border-line rounded-sm">
    <img src="{{ $sxImg }}" alt="{{ $sxAlt }}" class="block w-full h-auto" loading="lazy">
</a>
