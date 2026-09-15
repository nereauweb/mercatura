{{-- @mercatura-view frontend.pages.quotation-sent @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.quotation.sent_title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	@php $brand = config('brand'); @endphp
	<div class="mx-auto max-w-3xl px-4 py-10">
		<h1 class="text-2xl font-bold text-primary">{{ __('frontend.quotation.sent_title') }}</h1>
		<p class="mt-2 text-lg font-semibold text-primary">{{ __('frontend.quotation.sent_text') }}</p>
		@if($brand['contact']['phone'] || $brand['contact']['email'])
		<p class="mt-4">{{ __('frontend.quotation.sent_help', ['phone' => $brand['contact']['phone'], 'email' => $brand['contact']['email']]) }}</p>
		@endif
		<p class="mt-6"><a href="{{ route('frontend.home') }}" class="inline-flex items-center gap-1 font-semibold text-accent hover:underline"><x-frontend::icon name="chevron-left" class="h-4 w-4" />{{ __('frontend.checkout.back_home') }}</a></p>
	</div>
@endsection
@section('endpage_js')@if(isset($article))<x-frontend::tracking.quotation :value="$article->min_price()" />@endif@endsection
