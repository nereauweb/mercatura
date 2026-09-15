{{-- @mercatura-view frontend.public.seo-canonical @version 1 --}}
@if($emitCanonical ?? true)
<link rel="canonical" href="{{ $url }}" />
@endif
<meta property="og:url" content="{{ $url }}" />
