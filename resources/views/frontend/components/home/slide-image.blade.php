{{-- @mercatura-view frontend.components.home.slide-image @version 1 --}}
{{-- Hero <picture>: WebP srcset 800w/1600w, optional phone <source>. Original JPEG kept as fallback. --}}
@props(['slide', 'priority' => false, 'mobileMax' => 639, 'width' => 1600, 'height' => 420, 'alt' => '', 'imgClass' => 'h-full w-full object-cover'])
@php
    $background = \App\Support\HomeSlideImages::banner($slide->background_image);
    $mobile = $slide->mobile_image ? \App\Support\HomeSlideImages::banner($slide->mobile_image) : null;
@endphp
<picture {{ $attributes }}>
    @if($mobile)
    <source media="(max-width: {{ (int) $mobileMax }}px)" srcset="{{ $mobile['srcset'] !== '' ? $mobile['srcset'] : $mobile['src'] }}" sizes="100vw"@if($mobile['webp']) type="image/webp"@endif>
    @endif
    <img src="{{ $background['src'] }}"@if($background['srcset'] !== '') srcset="{{ $background['srcset'] }}" sizes="100vw"@endif alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}" class="{{ $imgClass }}" @if($priority) fetchpriority="high" decoding="sync" @else loading="lazy" decoding="async" @endif>
</picture>
