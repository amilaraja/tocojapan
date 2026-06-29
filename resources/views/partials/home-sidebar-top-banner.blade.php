@php
    $banner = $content['sidebar_top_banner'] ?? null;
    $img = is_array($banner) ? trim((string) ($banner['image'] ?? '')) : '';
    if ($img === '') {
        return;
    }
    if (! str_starts_with($img, '/') && ! str_starts_with($img, 'http')) {
        $img = '/storage/'.$img;
    }
    $url = is_array($banner) ? trim((string) ($banner['url'] ?? '')) : '';
    $title = is_array($banner) ? trim((string) ($banner['title'] ?? '')) : '';
    $alt = $title !== '' ? $title : 'Promotion';
@endphp

@if ($url !== '')
    <a href="{{ $url }}"
       @if (str_starts_with($url, 'http')) target="_blank" rel="noopener" @endif
       @if ($title !== '') title="{{ $title }}" @endif
       aria-label="{{ $alt }}"
       class="block overflow-hidden border border-line rounded-sm hover:border-toco-red transition">
        <img src="{{ $img }}" alt="{{ $alt }}" class="block w-full h-auto" loading="lazy">
    </a>
@else
    <div class="block overflow-hidden border border-line rounded-sm">
        <img src="{{ $img }}" alt="{{ $alt }}" class="block w-full h-auto" loading="lazy">
    </div>
@endif
