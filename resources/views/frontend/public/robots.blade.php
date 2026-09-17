{{-- @mercatura-view frontend.public.robots @version 1 --}}
{{-- robots.txt of a live installation; installations add lines through config mercatura.robots_extra. Staging instances answer "Disallow: /" instead. --}}
# Default rule for all bots
User-agent: *
Allow: /
# Block private/functional pages from all bots
Disallow: /carrello/
Disallow: /checkout/
Disallow: /admin/
# Block product listing page only (allow individual product pages like /prodotti/slug)
Disallow: /prodotti$
Disallow: /prodotti/$
Disallow: /ordine/
Disallow: /accedi/
Disallow: /logout/

@foreach((array) config('mercatura.robots_extra', []) as $line)
{{ $line }}
@endforeach
Sitemap: {{ url('/sitemap.xml') }}
