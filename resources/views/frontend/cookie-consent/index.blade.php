{{-- @mercatura-view frontend.cookie-consent.index @version 2 --}}
{{-- Consent banner (overlay). Behaviour: accept sets cookies_analytics=1 and loads GTM (or updates Consent Mode v2);
     reject sets cookies_analytics=0. Without a stored choice the banner shows on every page. --}}
@if($cookieConsentConfig['enabled'] && ! $alreadyConsentedWithCookies)
@php
    $config = [
        'cookieName' => $cookieConsentConfig['cookie_name'],
        'lifetimeDays' => (int) $cookieConsentConfig['cookie_lifetime'],
        'domain' => config('session.domain') ?: null,
        'secure' => (bool) config('session.secure'),
        'sameSite' => config('session.same_site') ?? 'Lax',
        'gtmId' => config('gtm.container_id'),
        'consentModeV2' => (bool) config('gtm.consentmode_v2'),
        'labels' => ['saved' => __('frontend.cookies.saved'), 'essentialOnly' => __('frontend.cookies.essential_only')],
    ];
@endphp
<div x-data="cookieConsent(@js($config))" class="pointer-events-none fixed inset-x-0 bottom-0 z-40 p-3 sm:bottom-4">
    <div x-show="visible" x-transition.opacity role="dialog" aria-label="Cookie" class="pointer-events-auto mx-auto max-w-xl rounded-card border border-border-muted bg-surface p-4 text-sm shadow-xl">
        <p class="flex items-start gap-2"><x-frontend::icon name="chat" class="mt-0.5 h-5 w-5 shrink-0 text-primary" /><span>{{ __('frontend.cookies.text') }} <a href="{{ route('frontend.contents.page', ['slug' => config('mercatura.legal_pages.privacy')]) }}" class="text-accent underline">{{ __('frontend.cookies.privacy_link') }}</a></span></p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="button" @click="reject()" class="rounded-card border border-border px-4 py-2 font-semibold hover:bg-surface-muted">{{ __('frontend.cookies.reject') }}</button>
            <button type="button" @click="accept()" class="rounded-card bg-primary px-4 py-2 font-semibold text-on-primary hover:bg-primary-strong">{{ __('frontend.cookies.accept') }}</button>
        </div>
    </div>
    <p x-cloak x-show="toast" x-transition.opacity x-text="toast" class="pointer-events-auto mx-auto mt-2 w-fit rounded-card bg-positive px-4 py-2 text-sm font-medium text-on-dark shadow-lg" aria-live="polite"></p>
</div>
@elseif(! config('gtm.consentmode_v2') && request()->cookie('cookies_analytics') === '1' && config('gtm.container_id'))
{{-- Consent already given: the head component loads GTM; nothing to do here. --}}
@endif
