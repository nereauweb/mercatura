{{-- @mercatura-view frontend.pages.checkout.account @version 2 --}}
{{-- Checkout step 2 for logged-in customers: fiscal and address data. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	@php $customer = Auth::user()->customer; @endphp
	<x-frontend::forms.customer-form :action="route('frontend.checkout.payment')" :type="$customer->customer_type ?? ''">
		<x-frontend::checkout.page :step="2" :cart="$cart" :crumbs="[['name' => __('frontend.cart.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.checkout.title'), 'url' => route('frontend.checkout.account')], ['name' => __('frontend.checkout.data'), 'url' => route('frontend.checkout.account')]]">
			<div class="rounded-card bg-surface-muted p-4 md:p-6">
				<x-frontend::forms.errors />
				<h1 class="sr-only">{{ __('frontend.checkout.data') }}</h1>
				<x-frontend::forms.customer-fields :customer="$customer?->toArray() ?? []" :billing="$customer?->billing_address?->toArray() ?? []" :shipping="$customer?->shipping_address?->toArray() ?? []" :provinces="$province_list" :with-notes="true" />
				<p class="mt-2 text-xs text-text-muted">{{ __('frontend.forms.required_hint') }}</p>
				<div class="mt-8 text-center">
					<button type="submit" class="rounded-card bg-accent px-8 py-3 font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __('frontend.checkout.proceed_to_payment') }}</button>
				</div>
			</div>
		</x-frontend::checkout.page>
	</x-frontend::forms.customer-form>
@endsection
