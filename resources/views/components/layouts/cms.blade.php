@php
    $seoTitle = $page->seo_title ?: $page->title;
    $seoDescription = $page->seo_description ?: '';
    $seoImage = $page->seo_image ?: null;
    $canonical = url('/'.$page->slug);
@endphp

<x-layouts.site
    :title="$seoTitle.' — Toco Japan'"
    :description="$seoDescription"
>
    @push('head')
        <link rel="canonical" href="{{ $canonical }}">
        @if ($seoTitle)
            <meta property="og:title" content="{{ $seoTitle }}">
        @endif
        @if ($seoDescription)
            <meta property="og:description" content="{{ $seoDescription }}">
        @endif
        @if ($seoImage)
            <meta property="og:image" content="{{ $seoImage }}">
        @endif
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:type" content="website">
    @endpush

    {{ $slot }}
</x-layouts.site>
