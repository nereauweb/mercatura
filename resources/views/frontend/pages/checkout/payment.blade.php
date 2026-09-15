{{-- @mercatura-view frontend.pages.checkout.payment @version 2 --}}
{{-- Checkout step 3: notices, consents, payment method. Methods come from config mercatura.checkout.payment_methods. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.payment').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	@php
		$blocked = null;
		if (Auth::check()) {
			$user = Auth::user();
			$blocked = $user->hasRole('admin') || ! $user->hasRole('customer') ? __('frontend.checkout.blocked_admin') : (! $user->customer ? __('frontend.checkout.blocked_customer_missing') : null);
		}
		$methods = config('mercatura.checkout.payment_methods', []);
		$logos = ['stripe' => [['/img/logo_visa.png', 'Visa', 80, 26], ['/img/logo_mastercard.png', 'Mastercard', 80, 50]], 'paypal' => [['/img/logo_paypal.png', 'PayPal', 120, 30]]];
	@endphp
	<x-frontend::checkout.page :step="3" :cart="$cart" :crumbs="[['name' => __('frontend.cart.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.checkout.title'), 'url' => route('frontend.checkout.account')], ['name' => __('frontend.checkout.payment'), 'url' => route('frontend.checkout.account')]]">
		<form method="POST" action="{{ route('frontend.checkout.finalize') }}" class="space-y-6 rounded-card bg-surface-muted p-4 md:p-6" @if($blocked) @submit.prevent @endif>
			@csrf
			<x-frontend::forms.errors />
			@if($blocked)<p class="rounded-card border border-danger bg-danger-soft p-3 text-sm text-danger" role="alert">{{ $blocked }}</p>@endif
			<section>
				<h1 class="text-lg font-bold text-primary">{{ __('frontend.checkout.notices_title') }}</h1>
				<ul class="mt-2 list-inside list-disc space-y-1 text-sm text-text-muted">
					<li>{{ __('frontend.checkout.notice_bank') }}</li>
					<li>{{ __('frontend.checkout.notice_printed') }}</li>
				</ul>
			</section>
			<section>
				<h2 class="text-lg font-bold text-primary">{{ __('frontend.checkout.privacy_terms') }}</h2>
				<x-frontend::forms.consents class="mt-2" />
			</section>
			<section>
				<h2 class="text-lg font-bold text-primary">{{ __('frontend.checkout.choose_payment') }}</h2>
				<ul class="mt-2 space-y-2">
					@foreach($methods as $method)
					<li><label class="flex cursor-pointer items-center gap-3 rounded-card border border-border-muted bg-surface p-3"><input type="radio" name="payment_method" value="{{ $method }}" required @checked(old('payment_method') === $method) class="accent-accent"><span class="text-base">{{ __('frontend.checkout.payment_'.$method) }}</span>
						@foreach($logos[$method] ?? [] as [$src, $alt, $w, $h])<img src="{{ $src }}" alt="{{ $alt }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" class="ml-auto h-6 w-auto">@endforeach
					</label></li>
					@endforeach
				</ul>
				@error('payment_method')<p class="mt-1 text-xs text-danger" data-field-error="payment_method">{{ $message }}</p>@enderror
			</section>
			<div class="text-center">
				<button type="submit" class="rounded-card bg-accent px-8 py-3 font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50" @disabled($blocked)>{{ __('frontend.checkout.proceed_to_payment') }}</button>
			</div>
		</form>
	</x-frontend::checkout.page>
@endsection
