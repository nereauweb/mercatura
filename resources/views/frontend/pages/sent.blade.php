{{-- @mercatura-view frontend.pages.sent @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.contact.sent_title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<div class="mx-auto max-w-7xl px-4 py-10">
		<div class="grid gap-8 md:grid-cols-3">
			<div class="md:col-span-2">
				<h1 class="text-2xl font-bold text-primary">{{ __('frontend.contact.sent_title') }}</h1>
				<p class="mt-2">{{ __('frontend.contact.sent_text') }}</p>
				<p class="mt-6"><a href="{{ route('frontend.home') }}" class="inline-flex items-center gap-1 font-semibold text-accent hover:underline"><x-frontend::icon name="chevron-left" class="h-4 w-4" />{{ __('frontend.checkout.back_home') }}</a></p>
			</div>
			<aside><h2 class="text-xl font-bold text-primary">{{ __('frontend.contact.contacts') }}</h2><x-frontend::contact-details class="mt-3" /></aside>
		</div>
	</div>
@endsection
