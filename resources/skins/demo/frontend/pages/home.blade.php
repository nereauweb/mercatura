{{-- @mercatura-view frontend.pages.home @version 2 --}}
{{-- Demo skin: a compact home built from brand config, lang keys and the core product card component.
     It proves a page-level override; the core home is untouched. --}}
@extends('frontend.public.layout')

@section('title', config('brand.name').' | '.config('brand.tagline'))

@push('head')
	<link rel="stylesheet" href="/skins/demo/demo.css">
@endpush

@section('head_meta')
	<x-frontend::seo.meta :title="config('brand.name').' | '.config('brand.tagline')" :description="config('brand.tagline')" :url="\App\Support\CanonicalUrl::absolute('/')" />
@endsection

@section('content')
	<div id="home-page" class="mx-auto max-w-7xl px-4 py-12">
		<section class="text-center">
			<img src="{{ config('brand.logo') }}" alt="{{ config('brand.name') }}" width="{{ config('brand.logo_width') }}" height="{{ config('brand.logo_height') }}" class="mx-auto h-12 w-auto">
			<h1 class="mt-4 text-3xl font-bold text-primary">{{ __('frontend.home.hero_title', ['brand' => config('brand.name')]) }}</h1>
			<p class="mt-2 text-lg text-text-muted">{{ __('frontend.home.hero_text') }}</p>
			<a href="{{ route('frontend.product.list') }}" class="mt-6 inline-block rounded-full bg-accent px-8 py-3 font-bold text-on-accent hover:bg-accent-strong">{{ __('frontend.home.hero_cta') }}</a>
		</section>
		@if(count($promo) > 0)
		<section class="mt-12">
			<h2 class="text-center text-2xl font-bold uppercase text-primary">{{ __('frontend.home.promo_title') }}</h2>
			<ul class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
				@foreach($promo->take(8) as $product)
					<li><x-frontend::product.card :product="$product" /></li>
				@endforeach
			</ul>
		</section>
		@endif
		<section class="mt-12 text-center text-sm text-text-muted">
			{{ config('brand.legal_name') }} · {{ config('brand.contact.email') }} · {{ config('brand.contact.phone') }}
		</section>
	</div>
@endsection
