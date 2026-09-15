{{-- @mercatura-view frontend.pages.checkout.finalized @version 2 --}}
{{-- Bank transfer order registered: bank details from config/brand.php. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.checkout.completed').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	@php $bank = config('brand.bank'); $phone = config('brand.contact.phone'); @endphp
	<x-frontend::checkout.outcome :title="__('frontend.checkout.bank_title')" :last-label="__('frontend.cart.step_registered')">
		<p>{{ __('frontend.checkout.bank_text') }}</p>
		@if($bank['iban'])
		<p>{{ __('frontend.checkout.bank_details') }}<br>
			{{ __('frontend.checkout.bank_iban') }}: <strong>{{ $bank['iban'] }}</strong><br>
			@if($bank['name']){{ __('frontend.checkout.bank_name') }}: <strong>{{ $bank['name'] }}</strong><br>@endif
			@if($bank['bic']){{ __('frontend.checkout.bank_bic') }}: <strong>{{ $bank['bic'] }}</strong>@endif
		</p>
		@endif
		<p>{{ __('frontend.checkout.bank_notice') }} @if($phone){{ __('frontend.checkout.bank_contact', ['phone' => $phone]) }}@endif</p>
		<p>{{ __('frontend.checkout.thanks_again') }}</p>
	</x-frontend::checkout.outcome>
@endsection
@section('endpage_js')<x-frontend::tracking.purchase :order="$order" />@endsection
