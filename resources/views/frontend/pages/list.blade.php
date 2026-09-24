{{-- @mercatura-view frontend.pages.list @version 4 --}}
{{-- Catalogue listing: category, brand or all products. Composes breadcrumb, sidebar navigation, bestsellers
     and the Livewire list with its filters. Data from FrontendListController::list_page.
     Stacks: catalog-before, catalog-after-bestsellers, catalog-after. --}}
@extends('frontend.public.layout')

@section('title', $page_title.' | '.config('brand.name'))

@section('head_meta')
	<x-frontend::seo.meta :title="$page_title" :description="$page_description" :url="$canonical" :noindex="$noindex" :model="$category ?? null" />
@endsection

@section('content')
	<div class="mx-auto max-w-7xl px-4 py-4" id="catalog-page">
		<x-frontend::breadcrumb :items="$breadcrumbs" class="mb-4 hidden md:block" />

		@stack('catalog-before')

		<h1 class="text-3xl font-bold uppercase text-primary">{{ $page_title }}</h1>

		@php $hasBestsellers = isset($bestsellers) && count($bestsellers) > 0; @endphp
		@if($hasBestsellers)
		<section class="my-6 rounded-card border border-border-muted p-4">
			<h2 class="mb-3 text-center text-xl font-bold text-primary">{{ __('frontend.catalog.bestsellers') }}</h2>
			<x-frontend::product.slider :products="$bestsellers" :priority="true" />
		</section>
		@endif

		@stack('catalog-after-bestsellers')

		@livewire('frontend-product-list', ['category' => $category ? $category->id : false, 'brand' => $selected_brand ?: false, 'prioritizeFirstCard' => ! $hasBestsellers], key('products-page'))

		@if($category)
			{{-- CMS text may carry its own h1: the page already has one, so headings are shifted down one level. --}}
			@if($category->description)
			<section class="prose mt-8 max-w-none border-t border-positive pt-6 text-primary">{!! preg_replace('/<(\/?)h1\b/i', '<$1h2', $category->description) !!}</section>
			@endif
			@if($category->extra_text)
			<section class="prose mt-6 max-w-none border-t border-positive pt-6">{!! preg_replace('/<(\/?)h1\b/i', '<$1h2', $category->extra_text) !!}</section>
			@endif
		@endif

		@stack('catalog-after')
	</div>
@endsection
