{{-- @mercatura-view frontend.pages.cart @version 2 --}}
{{-- Cart summary (step 1). Data: $cart from FrontendCartController::session_data_to_cart, $is_cart. Stacks: cart-before, cart-after. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.cart.title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<x-frontend::checkout.page :step="1" :cart="$cart" :is-cart="true" :crumbs="[['name' => __('frontend.cart.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.cart.summary'), 'url' => route('frontend.cart.index')]]">
		<a href="{{ route('frontend.home') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-accent hover:underline"><x-frontend::icon name="chevron-left" class="h-4 w-4" />{{ __('frontend.cart.continue_shopping') }}</a>
		<h1 class="sr-only">{{ __('frontend.cart.title') }}</h1>
		<x-frontend::forms.errors />
		@stack('cart-before')
		@if(config('mercatura.storefront.checkout') === 'onepage')
			<x-frontend::cart.table :cart="$cart" />
		@else
		<div class="space-y-4 rounded-card bg-surface-muted p-4">
			@forelse($cart['items'] as $item)
				<x-frontend::cart.item :item="$item" />
			@empty
				<p class="py-6 text-center font-semibold">{{ __('frontend.cart.empty') }}</p>
			@endforelse
		</div>
		@endif
		@stack('cart-after')
	</x-frontend::checkout.page>
@endsection
