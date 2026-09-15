{{-- @mercatura-view frontend.customer.contacts @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.account.messages').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<div class="mx-auto max-w-5xl px-4 py-10">
		<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
			<h1 class="text-2xl font-bold text-primary">{{ __('frontend.account.messages') }}</h1>
			<a href="{{ route('frontend.auth.reserved_area') }}" class="text-sm text-accent hover:underline">{{ __('frontend.nav.reserved_area') }}</a>
		</div>
		@livewire('frontend-contacts-table')
	</div>
@endsection
