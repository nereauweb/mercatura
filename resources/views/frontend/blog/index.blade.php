{{-- @mercatura-view frontend.blog.index @version 2 --}}
{{-- Article list with a client-side tag filter (Alpine); all articles are server-rendered. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.blog.title').' | '.config('brand.name'))
@section('head_meta')
	<x-frontend::seo.meta :title="__('frontend.blog.title')" :description="__('frontend.blog.subtitle')" :url="\App\Support\CanonicalUrl::absolute('/blog')" />
@endsection
@section('content')
	<div class="mx-auto max-w-7xl px-4 py-6" x-data="{ tag: @js($tagSlug ?? '') }">
		<header class="rounded-card bg-primary px-6 py-10 text-center text-on-dark">
			<h1 class="text-3xl font-bold">{{ __('frontend.blog.title') }}</h1>
			<p class="mt-2 opacity-90">{{ __('frontend.blog.subtitle') }}</p>
		</header>
		@if($articles->isNotEmpty())
			@if($tags->isNotEmpty())
			<ul class="my-6 flex flex-wrap gap-2 text-sm">
				<li><button type="button" @click="tag = ''" :class="tag === '' ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text'" class="rounded-full px-3 py-1 font-semibold">{{ __('frontend.blog.all') }}</button></li>
				@foreach($tags as $tagItem)
				<li><button type="button" @click="tag = @js($tagItem->slug)" :class="tag === @js($tagItem->slug) ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text'" class="rounded-full px-3 py-1 font-semibold">{{ $tagItem->name }}</button></li>
				@endforeach
			</ul>
			@endif
			<ul class="grid gap-6 lg:grid-cols-2">
				@foreach($articles as $article)
				@php $articleTags = $article->tags->pluck('slug')->all(); @endphp
				<li x-show="tag === '' || @js($articleTags).includes(tag)" class="{{ $loop->first ? 'lg:col-span-2' : '' }} overflow-hidden rounded-card border border-border-muted bg-surface shadow-sm">
					@if($article->cover)
					<a href="{{ route('frontend.blog.show', $article->slug) }}" class="relative block h-56">
						<img src="/storage/blog/img/{{ $article->cover }}" alt="{{ $article->title }}" width="800" height="450" loading="{{ $loop->first ? 'eager' : 'lazy' }}" class="h-full w-full object-cover">
						@if($article->tags->isNotEmpty())<span class="absolute left-3 top-3 rounded-full bg-surface/90 px-3 py-1 text-xs font-semibold">{{ $article->tags->first()->name }}</span>@endif
					</a>
					@endif
					<div class="flex flex-col gap-3 bg-surface-muted p-4">
						<h2 class="{{ $loop->first ? 'text-2xl' : 'text-lg' }} font-bold text-primary">{{ $article->title }}</h2>
						<p class="text-sm text-text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($article->excerpt ?: $article->text), $loop->first ? 200 : 160) }}</p>
						<p class="text-right"><a href="{{ route('frontend.blog.show', $article->slug) }}" class="inline-block rounded-full bg-accent px-4 py-1.5 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __('frontend.blog.read_more') }}</a></p>
					</div>
				</li>
				@endforeach
			</ul>
		@else
			<p class="mt-6 text-primary">{{ __('frontend.blog.no_articles') }}</p>
		@endif
	</div>
@endsection
