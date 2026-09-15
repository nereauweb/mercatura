{{-- @mercatura-view frontend.customer.index @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.nav.reserved_area').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<div class="mx-auto max-w-5xl px-4 py-10">
		<div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-muted pb-4">
			<h1 class="text-2xl font-bold text-primary">{{ __('frontend.account.welcome', ['name' => trim(Auth::user()->name.' '.(Auth::user()->surname ?? ''))]) }}</h1>
			<a href="{{ route('frontend.auth.logout') }}" class="inline-flex items-center gap-2 text-sm text-accent hover:underline"><x-frontend::icon name="close" class="h-4 w-4" />{{ __('frontend.account.logout') }}</a>
		</div>
		<ul class="mt-8 grid gap-4 sm:grid-cols-2 md:grid-cols-4">
			<li><a href="{{ route('frontend.auth.profile') }}" class="flex flex-col items-center gap-2 rounded-card border border-border-muted bg-surface p-6 text-center font-semibold text-primary shadow-sm hover:shadow-md"><x-frontend::icon name="user" class="h-8 w-8" />{{ __('frontend.account.profile') }}</a></li>
			<li><a href="{{ route('frontend.auth.order.list') }}" class="flex flex-col items-center gap-2 rounded-card border border-border-muted bg-surface p-6 text-center font-semibold text-primary shadow-sm hover:shadow-md"><x-frontend::icon name="document" class="h-8 w-8" />{{ __('frontend.account.orders') }}</a></li>
			@stack('account-links')
		</ul>
	</div>
@endsection
