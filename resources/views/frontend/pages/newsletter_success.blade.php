{{-- @mercatura-view frontend.pages.newsletter_success @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.newsletter.success_title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<div class="mx-auto max-w-3xl px-4 py-10">
		<h1 class="text-2xl font-bold text-primary">{{ __('frontend.newsletter.success_title') }}</h1>
		<p class="mt-2">{{ __('frontend.newsletter.success_text') }}</p>
		<p class="mt-6"><a href="{{ route('frontend.home') }}" class="inline-flex items-center gap-1 font-semibold text-accent hover:underline"><x-frontend::icon name="chevron-left" class="h-4 w-4" />{{ __('frontend.checkout.back_home') }}</a></p>
	</div>
@endsection
