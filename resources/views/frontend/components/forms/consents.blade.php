{{-- @mercatura-view frontend.components.forms.consents @version 1 --}}
{{-- Privacy, terms and newsletter checkboxes with links to the configured CMS pages. --}}
@props(['terms' => true, 'newsletter' => false, 'gdprName' => 'consent_gdpr', 'termsName' => 'consent_terms', 'gdprChecked' => false, 'newsletterChecked' => false])
@php
    $privacyUrl = route('frontend.contents.page', ['slug' => config('mercatura.legal_pages.privacy')]);
    $termsUrl = route('frontend.contents.page', ['slug' => config('mercatura.legal_pages.terms')]);
@endphp
<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <x-frontend::forms.checkbox :name="$gdprName" :checked="$gdprChecked" required>{!! __('frontend.forms.consent_gdpr', ['link' => '<a href="'.$privacyUrl.'" target="_blank" rel="noopener" class="text-accent underline">'.__('frontend.forms.consent_gdpr_link').'</a>']) !!}</x-frontend::forms.checkbox>
    @if($terms)
    <x-frontend::forms.checkbox :name="$termsName" required>{!! __('frontend.forms.consent_terms', ['link' => '<a href="'.$termsUrl.'" target="_blank" rel="noopener" class="text-accent underline">'.__('frontend.forms.consent_terms_link').'</a>']) !!}</x-frontend::forms.checkbox>
    @endif
    @if($newsletter)
    <x-frontend::forms.checkbox name="subscribe_newsletter" :checked="$newsletterChecked">{{ __('frontend.forms.subscribe_newsletter') }}</x-frontend::forms.checkbox>
    @endif
</div>
