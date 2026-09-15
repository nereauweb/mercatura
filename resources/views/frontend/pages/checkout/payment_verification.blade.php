{{-- @mercatura-view frontend.pages.checkout.payment_verification @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.verification_title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<x-frontend::checkout.outcome :title="__('frontend.checkout.verification_title')">
		@if(!empty($verification_message))
			<p>{{ $verification_message }}</p>
		@else
			<p>{{ __('frontend.checkout.verification_text') }}</p>
			<p>{{ __('frontend.checkout.verification_hint') }}</p>
		@endif
	</x-frontend::checkout.outcome>
@endsection
