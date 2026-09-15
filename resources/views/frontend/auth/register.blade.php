{{-- @mercatura-view frontend.auth.register @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.auth.register_title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,follow">@endsection
@section('content')
	<x-frontend::auth.card :title="__('frontend.auth.register_title')" width="max-w-5xl">
		<x-frontend::forms.customer-form :action="route('frontend.auth.register.submit')" captcha-field="register_id" captcha-action="register" class="rounded-card border border-border-muted bg-surface p-4 md:p-6">
			<x-frontend::forms.customer-fields :provinces="$province_list" :with-password="true" />
			<x-frontend::forms.consents :newsletter="true" class="mt-6" />
			<x-frontend::captcha field="register_id" action="register" class="mt-4" />
			<p class="mt-2 text-xs text-text-muted">{{ __('frontend.forms.required_hint') }}</p>
			<div class="mt-6 text-center">
				<button type="submit" class="rounded-card bg-accent px-8 py-3 font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __('frontend.forms.submit') }}</button>
			</div>
		</x-frontend::forms.customer-form>
		<p class="mt-4 text-center text-sm text-text-muted">{{ __('frontend.auth.have_account') }} <a href="{{ route('frontend.auth.login') }}" class="text-accent underline">{{ __('frontend.auth.login_link') }}</a></p>
	</x-frontend::auth.card>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
