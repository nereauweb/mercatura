{{-- @mercatura-view frontend.pages.checkout.login @version 2 --}}
{{-- Checkout step 2 for guests: login or password reset request. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<x-frontend::checkout.page :step="2" :cart="$cart" :crumbs="[['name' => __('frontend.cart.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.checkout.title'), 'url' => route('frontend.checkout.account')], ['name' => __('frontend.checkout.data'), 'url' => route('frontend.checkout.account')]]">
		<div class="space-y-6 rounded-card bg-surface-muted p-4 md:p-6">
			<x-frontend::forms.errors />
			<div class="flex flex-wrap items-baseline justify-between gap-2">
				<h1 class="text-xl font-bold text-primary">{{ __('frontend.checkout.have_account') }}</h1>
				<p class="text-sm">{{ __('frontend.checkout.or_register') }} <a href="{{ route('frontend.checkout.account.registration') }}" class="text-accent underline">{{ __('frontend.checkout.register_link') }}</a></p>
			</div>
			<form action="{{ route('frontend.checkout.account.login') }}" method="POST" class="grid gap-3 md:grid-cols-3 md:items-end" data-captcha-field="login_id" data-captcha-action="login">
				@csrf
				<x-frontend::forms.input name="email" type="email" :label="__('frontend.forms.email')" required autocomplete="email" />
				<x-frontend::forms.input name="password" type="password" :label="__('frontend.forms.password')" required autocomplete="current-password" />
				<div>
					<x-frontend::captcha field="login_id" action="login" />
					<button type="submit" class="w-full rounded-card bg-primary px-4 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.checkout.login') }}</button>
				</div>
			</form>
			<h2 class="text-lg font-bold text-primary">{{ __('frontend.checkout.forgot_password') }}</h2>
			<form action="{{ route('frontend.password_reset.request') }}" method="POST" class="grid gap-3 md:grid-cols-3 md:items-end" data-captcha-field="reset_password_id" data-captcha-action="reset_password">
				@csrf
				<x-frontend::forms.input name="email" id="reset-email" type="email" :label="__('frontend.forms.email')" required autocomplete="email" class="md:col-span-2" />
				<div>
					<x-frontend::captcha field="reset_password_id" action="reset_password" />
					<button type="submit" class="w-full rounded-card bg-primary px-4 py-2 text-sm font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.checkout.request_reset') }}</button>
				</div>
			</form>
		</div>
	</x-frontend::checkout.page>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
