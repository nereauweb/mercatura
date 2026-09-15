{{-- @mercatura-view frontend.pages.page @version 4 --}}
{{-- CMS page: optional cover with title and call to action, text, raw content, optional product list, extra text.
     Colour names stored with the page map to semantic tokens. Stacks: page-before, page-after. --}}
@extends('frontend.public.layout')
@php
    $tokens = ['bianco' => 'text-on-dark', 'blu-corporate' => 'text-primary', 'nero' => 'text-text', 'verde' => 'text-positive', 'arancione' => 'text-accent'];
    $token = fn ($name, $default) => $tokens[$name] ?? $default;
    $title = $page->seo_title ?: $page->title;
    $description = $page->seo_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $page->text), 160);
@endphp
@section('title', $title.' | '.config('brand.name'))
@section('head_meta')
	<x-frontend::seo.meta :title="$title" :description="$description" :url="\App\Support\CanonicalUrl::absolute('/contenuti/'.$page->slug)" :image="$page->cover ? '/storage/pages/img/'.$page->cover : null" :model="$page" />
@endsection
@section('content')
	<div class="mx-auto max-w-7xl px-4 py-6">
		@stack('page-before')
		@if($page->cover)
		<header class="relative mb-6 flex h-72 items-center overflow-hidden rounded-card bg-surface-muted">
			<img src="/storage/pages/img/{{ $page->cover }}" alt="" width="1200" height="288" class="absolute inset-0 h-full w-full object-cover" fetchpriority="high">
			<div class="relative p-6 md:p-8">
				@if($page->subtitle)<p class="text-lg font-semibold uppercase {{ $token($page->subtitle_color, 'text-on-dark') }}">{{ $page->subtitle }}</p>@endif
				<h1 class="text-3xl font-bold uppercase {{ $token($page->title_color, 'text-primary') }}">{{ $page->title }}</h1>
				@if($page->cta_text && $page->cta_link)<a href="{{ $page->cta_link }}" class="mt-6 inline-block rounded-full bg-accent px-6 py-3 font-bold text-on-accent shadow hover:bg-accent-strong">{{ $page->cta_text }}</a>@endif
			</div>
		</header>
		@else
		<h1 class="mb-4 text-3xl font-bold uppercase {{ $token($page->title_color, 'text-primary') }}">{{ $page->title }}</h1>
		@endif
		@if($page->text)<div class="prose max-w-none text-primary">{!! preg_replace('/<(\/?)h1\b/i', '<$1h2', $page->text) !!}</div>@endif
		@if($page->raw_content)<div class="prose mt-4 max-w-none">{!! preg_replace('/<(\/?)h1\b/i', '<$1h2', $page->raw_content) !!}</div>@endif
		@if($page->products && $page->contents)
			<div class="mt-8">@livewire('frontend-product-list', ['products_ids' => collect($page->products_ids())->map(fn ($id) => (int) $id)->values()->all()], key('page-products'))</div>
		@endif
		@if($page->extra_text)<div class="prose mt-6 max-w-none text-primary">{!! preg_replace('/<(\/?)h1\b/i', '<$1h2', $page->extra_text) !!}</div>@endif
		@stack('page-after')
	</div>
@endsection
