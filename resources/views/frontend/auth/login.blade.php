{{-- @mercatura-view frontend.auth.login @version 2 --}}
{{-- Login with a password-recovery toggle (Alpine, no server round-trip). --}}
@extends('frontend.public.layout')
@section('title', __('frontend.auth.login').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,follow">@endsection
@section('content')
	<x-frontend::auth.card :title="__('frontend.auth.login_title')" x-data="{ recover: false }">
		<form x-show="!recover" action="{{ route('frontend.login.attempt') }}" method="POST" class="space-y-3 rounded-card border border-border-muted bg-surface p-4" data-captcha-field="login_id" data-captcha-action="login">
			@csrf
			<x-frontend::forms.input name="email" type="email" :label="__('frontend.forms.email')" required autocomplete="email" />
			<x-frontend::forms.input name="password" type="password" :label="__('frontend.forms.password')" required autocomplete="current-password" />
			<x-frontend::captcha field="login_id" action="login" />
			<input type="hidden" name="status" value="doLogin">
			<button type="submit" class="w-full rounded-card bg-primary px-4 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.auth.login') }}</button>
		</form>
		<form x-cloak x-show="recover" action="{{ route('frontend.password_reset.request') }}" method="POST" class="space-y-3 rounded-card border border-border-muted bg-surface p-4" data-captcha-field="reset_password_id" data-captcha-action="reset_password">
			@csrf
			<x-frontend::forms.input name="email" id="recover-email" type="email" :label="__('frontend.forms.email')" required autocomplete="email" />
			<x-frontend::captcha field="reset_password_id" action="reset_password" />
			<button type="submit" class="w-full rounded-card bg-primary px-4 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.auth.request_new_password') }}</button>
		</form>
		<p class="mt-4 text-center text-sm">
			<button type="button" x-show="!recover" @click="recover = true" class="text-accent hover:underline">{{ __('frontend.auth.recover_password') }}</button>
			<button type="button" x-cloak x-show="recover" @click="recover = false" class="text-accent hover:underline">{{ __('frontend.auth.back_to_login') }}</button>
		</p>
		<p class="mt-2 text-center text-sm text-text-muted">{{ __('frontend.auth.no_account') }} <a href="{{ route('frontend.auth.register') }}" class="text-accent underline">{{ __('frontend.auth.register_link') }}</a></p>
	</x-frontend::auth.card>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
