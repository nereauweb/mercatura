{{-- @mercatura-view frontend.customer.message @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.account.messages').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	<div class="mx-auto max-w-3xl px-4 py-10">
		<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
			<h1 class="text-2xl font-bold text-primary">{{ __('frontend.contact.message') }}</h1>
			<a href="{{ route('frontend.contacts.list') }}" class="text-sm text-accent hover:underline">{{ __('frontend.account.back_to_messages') }}</a>
		</div>
		<dl class="divide-y divide-border-muted rounded-card border border-border-muted bg-surface text-sm">
			@foreach([__('frontend.account.message_date') => $message->created_at, __('frontend.account.message_from') => trim($message->name.' '.$message->surname), __('frontend.forms.phone') => $message->phone, __('frontend.forms.company') => $message->company, __('frontend.forms.activity') => $message->activity, __('frontend.contact.subject') => $message->subject, __('frontend.contact.message') => $message->message] as $label => $value)
				<div class="grid gap-1 px-4 py-2 sm:grid-cols-3"><dt class="text-text-muted">{{ $label }}</dt><dd class="sm:col-span-2 whitespace-pre-line">{{ $value }}</dd></div>
			@endforeach
		</dl>
	</div>
@endsection
