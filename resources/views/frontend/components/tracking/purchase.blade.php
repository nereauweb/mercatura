{{-- @mercatura-view frontend.components.tracking.purchase @version 1 --}}
{{-- Google Ads purchase conversion, only with a configured label and analytics consent. --}}
@props(['order'])
@if(config('gtm.google_ads_id') && config('gtm.conversions.purchase'))
<script>
(function(){try{if(typeof canTrackAnalytics==='function'&&canTrackAnalytics()){gtag('event','conversion',{'send_to':@json(config('gtm.google_ads_id').'/'.config('gtm.conversions.purchase')),'value':{{ (float) $order->total_price }},'currency':'EUR','transaction_id':@json((string) $order->id)});}}catch(e){}})();
</script>
@endif
