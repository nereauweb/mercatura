{{-- @mercatura-view frontend.pages.contact @version 2 --}}
{{-- Contact form with honeypot and captcha; contact details and optional map from config/brand.php. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.contact.title').' | '.config('brand.name'))
@section('head_meta')
	<x-frontend::seo.meta :title="__('frontend.contact.title')" :description="__('frontend.meta.default_description')" :url="\App\Support\CanonicalUrl::absolute('/contattaci')" />
@endsection
@section('content')
	@php $map = config('brand.contact.map_embed_url'); $c = $customer ?? null; @endphp
	<div class="mx-auto max-w-7xl px-4 py-6">
		@if($map)<div class="mb-6 overflow-hidden rounded-card"><iframe src="{{ $map }}" title="{{ __('frontend.contact.contacts') }}" width="100%" height="300" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="block w-full border-0"></iframe></div>@endif
		<div class="grid gap-8 md:grid-cols-3">
			<div class="md:col-span-2">
				<h1 class="text-2xl font-bold text-primary">{{ __('frontend.contact.form_title') }}</h1>
				<x-frontend::forms.errors class="mt-4" />
				<form action="{{ route('frontend.contacts.send') }}" method="POST" class="mt-4 grid gap-3 rounded-card border border-border-muted bg-surface p-4 md:grid-cols-2 md:p-6" x-data="customerForm(@js(['type' => old('customer_type', $c?->customer_type ?? ''), 'privateType' => \App\Models\Customer::TYPE_PRIVATE, 'publicAdminType' => \App\Models\Customer::TYPE_PUBLIC_ADMIN, 'companyType' => 'Azienda', 'messages' => []]))" data-captcha-field="contact_id" data-captcha-action="contact">
					@csrf
					<div class="hidden" aria-hidden="true"><label for="contact-website">Website</label><input type="text" name="website" id="contact-website" tabindex="-1" autocomplete="off" value=""></div>
					<x-frontend::forms.input name="name" :label="__('frontend.forms.name')" :value="$c?->name" required autocomplete="given-name" />
					<x-frontend::forms.input name="surname" :label="__('frontend.forms.surname')" :value="$c?->surname" required autocomplete="family-name" />
					<x-frontend::forms.select name="customer_type" id="customer-type" :label="__('frontend.forms.customer_type')" :options="\App\Models\Customer::CUSTOMER_TYPES" :value="$c?->customer_type" :placeholder="__('frontend.forms.select')" x-model="type" />
					<x-frontend::forms.input name="company" :label="__('frontend.forms.company')" :value="$c?->company" autocomplete="organization" />
					<x-frontend::forms.select name="activity" :label="__('frontend.forms.activity')" :options="\App\Models\Customer::$activities" :value="$c?->activity" :placeholder="__('frontend.forms.activity_none')" />
					<x-frontend::forms.input name="email" type="email" :label="__('frontend.forms.email')" :value="$c?->email" required autocomplete="email" />
					<x-frontend::forms.input name="phone" type="tel" :label="__('frontend.forms.phone')" :value="$c?->phone" autocomplete="tel" />
					<x-frontend::forms.input name="subject" :label="__('frontend.contact.subject')" required />
					<label class="block text-sm md:col-span-2"><span class="mb-1 block font-semibold">{{ __('frontend.contact.message') }} <em class="text-danger not-italic">*</em></span><textarea name="message" rows="4" required class="w-full rounded border {{ $errors->has('message') ? 'border-danger' : 'border-border' }} bg-surface px-3 py-2">{{ old('message') }}</textarea>@error('message')<p class="mt-1 text-xs text-danger" data-field-error="message">{{ $message }}</p>@enderror</label>
					<div class="md:col-span-2 space-y-2">
						<x-frontend::forms.consents :terms="false" />
						<x-frontend::forms.checkbox name="subscribe_newsletter">{{ __('frontend.contact.newsletter_optin') }}</x-frontend::forms.checkbox>
					</div>
					<div class="md:col-span-2">
						<x-frontend::captcha field="contact_id" action="contact" />
						<button type="submit" class="mt-2 rounded-card bg-primary px-6 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.contact.send') }}</button>
					</div>
				</form>
			</div>
			<aside>
				<h2 class="text-xl font-bold text-primary">{{ __('frontend.contact.contacts') }}</h2>
				<x-frontend::contact-details class="mt-3" />
			</aside>
		</div>
	</div>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
