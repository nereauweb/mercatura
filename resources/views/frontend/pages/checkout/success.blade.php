{{-- @mercatura-view frontend.pages.checkout.success @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.completed').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<x-frontend::checkout.outcome :title="__('frontend.checkout.thanks_title')" :last-label="__('frontend.cart.step_registered')">
		<p>{{ __('frontend.checkout.thanks_text') }}</p>
		<p>{{ __('frontend.checkout.thanks_help') }}</p>
	</x-frontend::checkout.outcome>
@endsection
@section('endpage_js')<x-frontend::tracking.purchase :order="$order" />@endsection
