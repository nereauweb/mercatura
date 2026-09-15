{{-- @mercatura-view frontend.components.tracking.gtm @version 1 --}}
{{-- Google Tag Manager, consent-gated. part="head" emits the loader, part="body" the noscript fallback.
     Legacy mode: GTM only when the analytics consent cookie is set. Consent Mode v2 (config gtm.consentmode_v2):
     GTM always, consent default denied, updated to granted when the cookie is set. --}}
@props(['part' => 'head'])
@php
    $container = config('gtm.container_id');
    $consented = request()->cookie('cookies_analytics') === '1';
    $consentModeV2 = (bool) config('gtm.consentmode_v2');
@endphp
@if($part === 'head')
<script>
function canTrackAnalytics(){function g(n){var v='; '+document.cookie,p=v.split('; '+n+'=');return p.length===2?p.pop().split(';').shift():null}return typeof gtag!=='undefined'&&g('cookies_analytics')==='1'}
</script>
@endif
@if($container)
    @if($part === 'head')
        @if($consentModeV2)
        <script>
        window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
        gtag('consent','default',{'ad_storage':'denied','ad_user_data':'denied','ad_personalization':'denied','analytics_storage':'denied'});
        </script>
        @endif
        @if($consentModeV2 || $consented)
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($container));</script>
        @endif
    @else
        @if($consentModeV2 || $consented)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $container }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        @if($consentModeV2 && $consented)
        <script>gtag('consent','update',{'ad_storage':'granted','ad_user_data':'granted','ad_personalization':'granted','analytics_storage':'granted'});</script>
        @endif
    @endif
@endif
