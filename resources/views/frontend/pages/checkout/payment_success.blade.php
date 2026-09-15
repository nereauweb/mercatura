{{-- @mercatura-view frontend.pages.checkout.payment_success @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.completed').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<x-frontend::checkout.outcome :title="__('frontend.checkout.thanks_title')">
		<p>{{ __('frontend.checkout.thanks_text') }}</p>
		<p>{{ __('frontend.checkout.thanks_help') }}</p>
	</x-frontend::checkout.outcome>
@endsection
@section('endpage_js')@if(isset($order))<x-frontend::tracking.purchase :order="$order" />@endif@endsection
