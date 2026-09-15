{{-- @mercatura-view frontend.pages.newsletter @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.newsletter.title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,follow">@endsection
@section('content')
	<x-frontend::auth.card :title="__('frontend.newsletter.title')" width="max-w-3xl">
		<form action="{{ route('frontend.newsletter.register') }}" method="POST" class="grid gap-3 rounded-card border border-border-muted bg-surface p-4 sm:grid-cols-2 md:p-6" x-data="customerForm(@js(['type' => old('customer_type', ''), 'privateType' => \App\Models\Customer::TYPE_PRIVATE, 'publicAdminType' => \App\Models\Customer::TYPE_PUBLIC_ADMIN, 'companyType' => 'Azienda', 'messages' => []]))" data-captcha-field="subscribe_nl_id" data-captcha-action="subscribe_nl">
			@csrf
			<div class="hidden" aria-hidden="true"><label for="newsletter-website">Website</label><input type="text" name="website" id="newsletter-website" tabindex="-1" autocomplete="off" value=""></div>
			<x-frontend::forms.input name="name" :label="__('frontend.forms.name')" required autocomplete="given-name" />
			<x-frontend::forms.input name="surname" :label="__('frontend.forms.surname')" required autocomplete="family-name" />
			<x-frontend::forms.input name="email" type="email" :label="__('frontend.forms.email')" required autocomplete="email" />
			<x-frontend::forms.input name="phone" type="tel" :label="__('frontend.forms.phone')" required autocomplete="tel" />
			<x-frontend::forms.select name="customer_type" id="customer-type" :label="__('frontend.forms.customer_type')" :options="\App\Models\Customer::CUSTOMER_TYPES" :placeholder="__('frontend.forms.select')" x-model="type" />
			<x-frontend::forms.select name="activity" :label="__('frontend.forms.activity')" :options="\App\Models\Customer::$activities" :placeholder="__('frontend.forms.activity_none')" x-cloak x-show="show('activity')" />
			<x-frontend::forms.input name="company" :label="__('frontend.forms.company')" x-cloak x-show="show('company')" ::required="required('company')" autocomplete="organization" />
			<div class="sm:col-span-2"><x-frontend::forms.consents :terms="false" /></div>
			<div class="sm:col-span-2 text-center">
				<x-frontend::captcha field="subscribe_nl_id" action="subscribe_nl" />
				<button type="submit" class="mt-2 rounded-card bg-primary px-6 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.forms.submit') }}</button>
			</div>
		</form>
	</x-frontend::auth.card>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
