{{-- @mercatura-view frontend.components.seo.meta @version 2 --}}
{{-- Meta description, robots, canonical, Open Graph and Twitter tags for a page.
     Pass :model (a model using HasSeoFields) to apply its stored canonical_url, noindex and og_* overrides. --}}
@props(['title', 'description', 'url', 'image' => null, 'type' => 'website', 'canonical' => true, 'noindex' => false, 'model' => null])
@php
    if ($model) {
        $url = $model->resolvedCanonicalUrl($url);
        $noindex = $noindex || $model->isNoindex();
        $ogTitle = $model->resolvedOgTitle($title);
        $ogDescription = $model->resolvedOgDescription($description);
        $image = $model->resolvedOgImage($image);
    } else {
        $ogTitle = $title;
        $ogDescription = $description;
    }
    $image = $image ?: config('brand.og_image');
    $imageUrl = str_starts_with((string) $image, 'http') ? $image : rtrim(url('/'), '/').$image;
@endphp
<meta name="description" content="{{ $description }}">
@if($noindex)
<meta name="robots" content="noindex,follow">
@endif
@include('frontend.public.seo-canonical', ['url' => $url, 'emitCanonical' => $canonical && ! $noindex])
<meta property="og:type" content="{{ $type }}" />
<meta property="og:title" content="{{ $ogTitle }}" />
<meta property="og:description" content="{{ $ogDescription }}" />
<meta property="og:image" content="{{ $imageUrl }}" />
<meta property="og:image:alt" content="{{ $ogTitle }}" />
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
<meta name="twitter:image" content="{{ $imageUrl }}">
