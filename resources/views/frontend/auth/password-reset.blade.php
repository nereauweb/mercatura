{{-- @mercatura-view frontend.auth.password-reset @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.auth.reset_title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<x-frontend::auth.card :title="__('frontend.auth.reset_title')">
		<form action="{{ route('frontend.password_reset.submit') }}" method="POST" class="space-y-3 rounded-card border border-border-muted bg-surface p-4" data-captcha-field="reset_password_id" data-captcha-action="reset_password">
			@csrf
			<input type="hidden" name="token" value="{{ $token }}">
			<x-frontend::forms.input name="email" type="email" :label="__('frontend.forms.email')" :value="request('email', '')" required autocomplete="email" />
			<x-frontend::forms.input name="password" type="password" :label="__('frontend.forms.new_password')" required minlength="8" autocomplete="new-password" />
			<x-frontend::forms.input name="password_confirmation" type="password" :label="__('frontend.auth.reset_password_confirmation')" required minlength="8" autocomplete="new-password" />
			<x-frontend::captcha field="reset_password_id" action="reset_password" />
			<button type="submit" class="w-full rounded-card bg-primary px-4 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong">{{ __('frontend.forms.submit') }}</button>
		</form>
	</x-frontend::auth.card>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
