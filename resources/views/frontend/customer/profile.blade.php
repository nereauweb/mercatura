{{-- @mercatura-view frontend.customer.profile @version 2 --}}
{{-- Two forms: user account (email, name, password) and customer data (shared customer fields). --}}
@extends('frontend.public.layout')
@section('title', __('frontend.account.profile').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	@php $user = Auth::user(); $customer = $user->customer; @endphp
	<x-frontend::auth.card :title="__('frontend.account.user_profile')" width="max-w-5xl">
		<form action="{{ route('frontend.auth.profile.user.update') }}" method="POST" autocomplete="off" class="grid gap-3 rounded-card border border-border-muted bg-surface p-4 sm:grid-cols-2 md:p-6" data-captcha-field="user_autoupdate_id" data-captcha-action="user_autoupdate">
			@csrf
			<x-frontend::forms.input name="email" id="user-email" type="email" :label="__('frontend.forms.email')" :value="$user->email" required />
			<x-frontend::forms.input name="name" id="user-name" :label="__('frontend.account.username')" :value="$user->name" required />
			<x-frontend::forms.input name="password" id="user-password" type="password" :label="__('frontend.account.new_password_hint')" minlength="8" autocomplete="new-password" />
			<x-frontend::forms.input name="password_confirmation" id="user-password-confirmation" type="password" :label="__('frontend.forms.password_confirmation')" minlength="8" autocomplete="new-password" />
			<div class="sm:col-span-2 text-center">
				<x-frontend::captcha field="user_autoupdate_id" action="user_autoupdate" />
				<button type="submit" class="mt-2 rounded-card bg-primary px-6 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.account.update') }}</button>
			</div>
		</form>

		<h2 class="mb-4 mt-10 text-center text-2xl font-bold text-primary">{{ __('frontend.account.customer_profile') }}</h2>
		<x-frontend::forms.customer-form :action="route('frontend.auth.profile.customer.update')" :type="$customer->customer_type ?? ''" captcha-field="customer_autoupdate_id" captcha-action="customer_autoupdate" class="rounded-card border border-border-muted bg-surface p-4 md:p-6">
			<x-frontend::forms.customer-fields :customer="$customer?->toArray() ?? []" :billing="$customer?->billing_address?->toArray() ?? []" :shipping="$customer?->shipping_address?->toArray() ?? []" :provinces="$province_list" />
			<div class="mt-6 text-center">
				<x-frontend::captcha field="customer_autoupdate_id" action="customer_autoupdate" />
				<button type="submit" class="mt-2 rounded-card bg-primary px-6 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.account.update') }}</button>
			</div>
		</x-frontend::forms.customer-form>
	</x-frontend::auth.card>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
