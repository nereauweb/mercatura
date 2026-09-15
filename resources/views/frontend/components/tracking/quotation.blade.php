{{-- @mercatura-view frontend.components.tracking.quotation @version 1 --}}
{{-- Google Ads quotation conversion, only with a configured label and analytics consent. --}}
@props(['value' => 0])
@if(config('gtm.google_ads_id') && config('gtm.conversions.quotation_sent'))
<script>
(function(){try{if(typeof canTrackAnalytics==='function'&&canTrackAnalytics()){gtag('event','conversion',{'send_to':@json(config('gtm.google_ads_id').'/'.config('gtm.conversions.quotation_sent')),'value':{{ (float) $value }},'currency':'EUR'});}}catch(e){}})();
</script>
@endif
