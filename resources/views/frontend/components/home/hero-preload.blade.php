{{-- @mercatura-view frontend.components.home.hero-preload @version 1 --}}
{{-- Preload the LCP hero (first slide). Pair with slide-image srcset; media matches the slideshow <source>. --}}
@props(['slide' => null, 'mobileMax' => 639])
@if($slide && $slide->background_image)
@php
    $background = \App\Support\HomeSlideImages::banner($slide->background_image);
    $mobile = $slide->mobile_image ? \App\Support\HomeSlideImages::banner($slide->mobile_image) : null;
@endphp
	@if($mobile)
	<link rel="preload" as="image" href="{{ $mobile['src'] }}"@if($mobile['srcset'] !== '') imagesrcset="{{ $mobile['srcset'] }}" imagesizes="100vw"@endif media="(max-width: {{ (int) $mobileMax }}px)" fetchpriority="high">
	<link rel="preload" as="image" href="{{ $background['src'] }}"@if($background['srcset'] !== '') imagesrcset="{{ $background['srcset'] }}" imagesizes="100vw"@endif media="(min-width: {{ (int) $mobileMax + 1 }}px)" fetchpriority="high">
	@else
	<link rel="preload" as="image" href="{{ $background['src'] }}"@if($background['srcset'] !== '') imagesrcset="{{ $background['srcset'] }}" imagesizes="100vw"@endif fetchpriority="high">
	@endif
@endif
