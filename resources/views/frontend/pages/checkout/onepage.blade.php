{{-- @mercatura-view frontend.pages.checkout.onepage @version 1 --}}
{{-- Onepage checkout (docs/04_STOREFRONT_FLOWS.md §4.7): the whole flow is the frontend-checkout-onepage Livewire component. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.onepage.title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<div id="checkout" class="mx-auto max-w-7xl px-4 py-4">
		<x-frontend::breadcrumb :items="[['name' => __('frontend.cart.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.onepage.title'), 'url' => route('frontend.checkout.onepage')]]" class="mb-4 hidden md:block" />
		<h1 class="mb-4 text-2xl font-bold text-primary">{{ __('frontend.onepage.title') }}</h1>
		@livewire('frontend-checkout-onepage')
	</div>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
