@php
    $sx = $content['seasonal'] ?? [];
    // The sidebar banner is opt-in via the dedicated `sidebar_image` field on
    // the home page editor. Falls back to the wide top-strip image only when
    // the strip is enabled and no dedicated sidebar image is set.
    $sxImg = $sx['sidebar_image'] ?? null;
    if (! $sxImg && ($sx['enabled'] ?? true) && ! empty($sx['image'])) {
        $sxImg = $sx['image'];
    }
    if (! $sxImg) {
        return;
    }
    if (! str_starts_with($sxImg, '/') && ! str_starts_with($sxImg, 'http')) {
        $sxImg = '/storage/'.$sxImg;
    }
    $sxUrl = $sx['sidebar_url'] ?? $sx['cta_url'] ?? route('vehicles.index');
    $sxAlt = $sx['tag'] ?? $sx['text'] ?? 'Seasonal promotion';
@endphp
<a href="{{ $sxUrl }}" aria-label="{{ $sxAlt }}" class="block overflow-hidden border border-line rounded-sm hover:border-toco-red transition">
    <img src="{{ $sxImg }}" alt="{{ $sxAlt }}" class="block w-full h-auto" loading="lazy">
</a>
