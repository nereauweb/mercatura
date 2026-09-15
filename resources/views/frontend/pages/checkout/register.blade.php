{{-- @mercatura-view frontend.pages.checkout.register @version 2 --}}
{{-- Checkout step 2 for new customers: registration with fiscal and address data. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.register').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<x-frontend::forms.customer-form :action="route('frontend.checkout.account.register')" captcha-field="register_id" captcha-action="register">
		<x-frontend::checkout.page :step="2" :cart="$cart" :crumbs="[['name' => __('frontend.cart.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.checkout.title'), 'url' => route('frontend.checkout.account')], ['name' => __('frontend.checkout.data'), 'url' => route('frontend.checkout.account')]]">
			<div class="rounded-card bg-surface-muted p-4 md:p-6">
				<x-frontend::forms.errors />
				<div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
					<h1 class="text-xl font-bold text-primary">{{ __('frontend.checkout.register') }}</h1>
					<p class="text-sm">{{ __('frontend.checkout.or_login') }} <a href="{{ route('frontend.checkout.account') }}" class="text-accent underline">{{ __('frontend.checkout.login_link') }}</a></p>
				</div>
				<x-frontend::forms.customer-fields :provinces="$province_list" :with-password="true" />
				<x-frontend::forms.consents :newsletter="true" class="mt-6" />
				<x-frontend::captcha field="register_id" action="register" class="mt-4" />
				<div class="mt-6 text-center">
					<button type="submit" class="rounded-card bg-accent px-8 py-3 font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __('frontend.checkout.save_and_pay') }}</button>
				</div>
			</div>
		</x-frontend::checkout.page>
	</x-frontend::forms.customer-form>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
