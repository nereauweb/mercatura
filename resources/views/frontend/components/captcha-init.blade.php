{{-- @mercatura-view frontend.components.captcha-init @version 2 --}}
{{-- Captcha bootstrap script from the configured CaptchaProvider, once per page that has captcha fields. Push it to the scripts stack or the endpage_js section. --}}
{!! app(\App\Contracts\CaptchaProvider::class)->renderScript() !!}
