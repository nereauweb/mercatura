{{-- @mercatura-view frontend.pages.home @version 4 --}}
{{-- Home: hero slideshow, selling points, promo products, top categories, green products, newsletter.
     Data from FrontendContentController: $slides, $promo, $green; $nav_categories shared by AppServiceProvider.
     Stacks: home-before, home-after-hero, home-after-promo, home-after. --}}
@extends('frontend.public.layout')

@php
    $brand = config('brand');
    $pageTitle = __('frontend.home.title', ['brand' => $brand['name'], 'tagline' => $brand['tagline']]);
    $homeCategories = array_slice($nav_categories ?? [], 0, 6);
@endphp

@section('title', $pageTitle)

@section('head_meta')
	<x-frontend::seo.meta :title="$pageTitle" :description="__('frontend.meta.default_description')" :url="\App\Support\CanonicalUrl::absolute('/')" />
@endsection

@push('head')
	<x-frontend::home.hero-preload :slide="isset($slides) ? $slides->first() : null" :mobile-max="639" />
@endpush

@section('content')
	<h1 class="sr-only">{{ $pageTitle }}</h1>
	<div id="home-page" class="mx-auto max-w-7xl px-4">
		@stack('home-before')

		<x-frontend::home.slideshow :slides="$slides" class="mt-4" />

		<x-frontend::usp class="my-8" />

		@stack('home-after-hero')

		@if(count($promo))
		<section class="my-10 border-t border-positive pt-8 text-center">
			<h2 class="text-2xl font-bold uppercase text-primary">{{ __('frontend.home.promo_title') }}</h2>
			<p class="mb-4 text-text-muted">{{ __('frontend.home.promo_subtitle') }} <a href="{{ route('frontend.product.list') }}" class="font-semibold text-accent hover:underline">{{ __('frontend.home.promo_link') }}</a></p>
			<x-frontend::home.product-slider :products="$promo" />
		</section>
		@endif

		@stack('home-after-promo')

		@if($homeCategories)
		<section class="my-10 text-center">
			<h2 class="text-2xl font-bold uppercase text-primary">{{ __('frontend.home.categories_title') }}</h2>
			<ul class="mt-6 grid gap-6 text-left sm:grid-cols-2 md:grid-cols-3">
				@foreach($homeCategories as $category)
					<li>
						<a href="{{ route('frontend.category.show.by_slug', ['slug' => $category['slug']]) }}" class="group block h-full rounded-card border border-border-muted bg-surface p-5 shadow-sm transition hover:shadow-md">
							<div class="flex items-center gap-3">
								@if($category['icon'])
									<img src="/storage/categories/icons/{{ $category['icon'] }}" alt="" width="48" height="48" loading="lazy" class="h-12 w-12 object-contain">
								@endif
								<h3 class="text-lg font-bold text-primary group-hover:text-accent">{{ $category['name'] }}</h3>
							</div>
							@if(!empty($category['description']))
								<p class="mt-3 text-sm text-text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($category['description']), 160) }}</p>
							@endif
							<span class="mt-3 inline-block text-sm font-semibold text-accent">{{ __('frontend.home.categories_link') }} →</span>
						</a>
					</li>
				@endforeach
			</ul>
		</section>
		@endif

		@if(count($green))
		<section class="my-10 -mx-4 bg-positive px-4 py-8 text-center text-on-dark md:rounded-card">
			<h2 class="text-2xl font-bold uppercase">{{ __('frontend.home.green_title') }}</h2>
			<p class="font-semibold">{{ __('frontend.home.green_subtitle') }}</p>
			<p class="mx-auto mt-3 max-w-3xl text-sm">{{ __('frontend.home.green_text') }}</p>
			<x-frontend::home.product-slider :products="$green" class="mt-6 text-text" />
			@if(!empty($brand['certifications']))
				<div class="mt-6 flex flex-wrap items-center justify-center gap-6">
					<h3 class="text-lg font-bold uppercase">{{ __('frontend.home.certifications_title') }}</h3>
					@foreach($brand['certifications'] as $certification)
						<img src="{{ $certification['image'] }}" alt="{{ $certification['label'] }}" width="{{ $certification['width'] ?? 60 }}" height="{{ $certification['height'] ?? 60 }}" loading="lazy" class="h-12 w-auto">
					@endforeach
				</div>
			@endif
		</section>
		@endif

		<section class="my-10 flex flex-col items-center gap-4 rounded-card bg-surface-muted p-6 md:flex-row md:justify-between">
			<h2 class="text-center text-xl font-semibold text-text-muted md:text-left">{{ __('frontend.home.newsletter_title') }}</h2>
			<a href="{{ route('frontend.newsletter.index') }}" id="open-newsletter-box" class="shrink-0 rounded-full bg-accent px-8 py-3 text-lg font-bold text-on-accent shadow hover:bg-accent-strong">{{ __('frontend.home.newsletter_cta') }}</a>
		</section>

		@stack('home-after')
	</div>
@endsection
