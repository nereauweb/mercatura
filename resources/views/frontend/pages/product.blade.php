{{-- @mercatura-view frontend.pages.product @version 3 --}}
{{-- Product page for one variant: gallery, identity, colours, description, CTAs, print info, price tables,
     details, configurator, related products, selling points. Data: $product, $article, $page (ProductPageData).
     Stacks: product-before, product-after-price, product-after-description, product-after-tables, product-after. --}}
@extends('frontend.public.layout')

@php
    $brand = config('brand');
    $title = $product->get_seo_title();
    $ogImage = $product->getOpenGraphImageInfo();
    $quoteUrl = route('frontend.quotation.configure', ['id' => $article->id]);
    $badges = array_filter([
        $product->isBestseller() ? ['label' => __('frontend.product.card.badge_bestseller'), 'class' => 'bg-accent text-on-accent'] : null,
        $product->isPromo() ? ['label' => __('frontend.product.card.badge_promo'), 'class' => 'bg-danger text-on-accent'] : null,
        $product->isGreen() ? ['label' => __('frontend.product.card.badge_green'), 'class' => 'bg-positive text-on-accent'] : null,
        $product->isSale() ? ['label' => __('frontend.product.card.badge_sale'), 'class' => 'bg-danger text-on-accent'] : null,
        $product->isNew() ? ['label' => __('frontend.product.card.badge_new'), 'class' => 'bg-primary text-on-primary'] : null,
    ]);
    $defaultPrinting = $has_printing ? $article->defaultCustomization() : null;
    $hasPrices = $article->prices->count() > 0 && ! $product->quote_only;
    $configuratorConfig = [
        'endpoints' => [
            'summary' => route('frontend.product.build_articles_request'),
            'sizes' => route('frontend.product.get.printing_image_and_sizes'),
            'colors' => route('frontend.product.get.options'),
        ],
        'csrf' => csrf_token(),
        'hasPrinting' => $page['configurator']['has_printing'],
        'hasPackaging' => $page['configurator']['has_packaging'],
        'minQuantity' => $page['configurator']['min_quantity'],
        'positions' => $page['configurator']['positions'],
        'labels' => ['fourColour' => __('frontend.product.configurator.four_colour'), 'selectVariantFirst' => __('frontend.product.configurator.select_variant_first')],
        'conversion' => config('gtm.google_ads_id') && config('gtm.conversions.add_to_cart') ? config('gtm.google_ads_id').'/'.config('gtm.conversions.add_to_cart') : null,
    ];
@endphp

@section('title', $title.' | '.$brand['name'])

@section('head_meta')
	<x-frontend::seo.meta :title="$title" :description="$product->get_seo_description()" :url="$product->canonical_url()" :image="$ogImage['url'] ?? null" type="product" :model="$product" />
	@if($product->brand)<meta property="product:brand" content="{{ $product->brand }}" />@endif
	@if($ogImage)<meta property="og:image:width" content="{{ $ogImage['width'] }}" /><meta property="og:image:height" content="{{ $ogImage['height'] }}" /><meta property="og:image:type" content="{{ $ogImage['type'] }}" />@endif
@endsection

@section('content')
	<div id="product" class="mx-auto max-w-7xl px-4 py-4" x-data="productConfigurator(@js($configuratorConfig))">
		<x-frontend::breadcrumb :items="$page['breadcrumbs']" class="mb-4 hidden md:block" />
		@stack('product-before')

		<section class="grid gap-8 md:grid-cols-5">
			<div class="md:col-span-2">
				<x-frontend::product.gallery :images="$page['gallery']" :alt="$product->name" :badges="$badges" />
			</div>
			<div class="flex flex-col gap-4 md:col-span-3">
				<div>
					<h1 class="text-2xl font-bold text-primary md:text-3xl">{{ $product->name }}</h1>
					<p class="text-sm text-text-muted">{{ __('frontend.product.code') }} <strong>{{ $article->sku }}</strong></p>
				</div>
				@if($page['colors'])
				<ul class="flex flex-wrap gap-2" aria-label="{{ __('frontend.product.colors') }}">
					@foreach($page['colors'] as $color)
						<li><a href="{{ $color['url'] }}" title="{{ $color['label'] }}" class="block h-5 w-5 rounded-full border {{ $color['current'] ? 'border-accent ring-2 ring-accent' : 'border-border' }}" style="{{ $color['code'] }}"><span class="sr-only">{{ $color['label'] }}</span></a></li>
					@endforeach
				</ul>
				@endif
				@if($product->description)
				<div>
					<h2 class="text-sm font-bold uppercase text-primary">{{ __('frontend.product.description') }}</h2>
					<div class="prose max-w-none text-sm">{!! $product->description !!}</div>
				</div>
				@endif
				@stack('product-after-description')
				@if($product->quote_only)
				<p class="rounded-card bg-primary-soft p-3 text-sm">{{ __('frontend.product.quote_only') }}</p>
				@endif
				<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
					<a href="{{ $quoteUrl }}" class="rounded-card bg-accent px-4 py-2 text-center text-sm font-bold uppercase text-on-accent shadow hover:bg-accent-strong">{{ __('frontend.product.request_quote') }}</a>
					@if($hasPrices)
					<button type="button" @click="show()" class="rounded-card bg-primary px-4 py-2 text-center text-sm font-bold uppercase text-on-primary shadow hover:bg-primary-strong">{{ __('frontend.product.buy') }}</button>
					@endif
				</div>
				@if($hasPrices)
				<p class="text-sm">{{ __('frontend.product.availability_for_color', ['count' => $product->color_variants_stock($article->color_id)]) }} <button type="button" @click="show()" class="text-accent hover:underline">{{ __('frontend.product.view_all_availability') }}</button></p>
				@endif
				@stack('product-after-price')
				<div class="grid gap-3 md:grid-cols-2">
					@if($defaultPrinting)
					<div class="flex gap-3 rounded-card bg-surface-muted p-3 text-sm">
						<x-frontend::icon name="pencil" class="h-8 w-8 shrink-0 text-primary" />
						<div>
							<p>{{ __('frontend.product.recommended_technique') }}:</p>
							<p class="font-semibold uppercase text-primary">{{ $defaultPrinting->technique_label }} {{ $defaultPrinting->position_label }}</p>
							<p class="text-primary">{{ __('frontend.product.print_area', ['size' => $defaultPrinting->defaultAreaLabel()]) }}</p>
							<p class="text-primary">{{ __('frontend.product.up_to_colours', ['count' => $defaultPrinting->maxColors()]) }}</p>
						</div>
					</div>
					@endif
					@if($product->processing_days())
					<div class="flex gap-3 rounded-card bg-surface-muted p-3 text-sm">
						<x-frontend::icon name="clock" class="h-8 w-8 shrink-0 text-primary" />
						<div>
							<p>{{ __('frontend.product.production_days', ['days' => $product->processing_days()]) }}</p>
							@if($brand['contact']['phone'])<p>{{ __('frontend.product.fast_shipping', ['phone' => '']) }} <a href="tel:{{ preg_replace('/[^\d+]/', '', $brand['contact']['phone']) }}" class="text-accent hover:underline">{{ $brand['contact']['phone'] }}</a></p>@endif
						</div>
					</div>
					@endif
				</div>
			</div>
		</section>

		<section class="mt-8 grid gap-8 md:grid-cols-5">
			@if($hasPrices)
			<div class="md:col-span-2">
				<h2 class="text-lg font-bold uppercase text-primary">{{ __('frontend.product.price_table') }}</h2>
				<x-frontend::product.price-table :table="$page['price_table']" :quote-url="$quoteUrl" class="mt-2" />
			</div>
			@endif
			<div class="{{ $hasPrices ? 'md:col-span-3' : 'md:col-span-5' }}">
				<x-frontend::product.details :details="$page['details']" :packaging="$page['packaging']" />
			</div>
		</section>
		@stack('product-after-tables')

		@if($hasPrices)
			<x-frontend::product.configurator :product="$product" :article="$article" :configurator="$page['configurator']" />
		@endif

		@if(count($page['related']))
		<section class="mt-10">
			<h2 class="mb-3 text-center text-xl font-bold uppercase text-primary">{{ __('frontend.product.related') }}</h2>
			<x-frontend::product.slider :products="$page['related']" />
		</section>
		@endif

		<x-frontend::usp class="my-10 border-t border-positive pt-8" />
		@stack('product-after')
	</div>
@endsection

@section('endpage_js')
<script type="application/ld+json">{!! json_encode($productStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
