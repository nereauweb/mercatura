{{-- @mercatura-view frontend.pages.quotation @version 2 --}}
{{-- Quotation request: customer data, the product being configured ($article) or edited ($edit_quotation_item),
     the products already in the request ($quotation['products']). Alpine quotationForm handles client checks. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.quotation.title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	@php
		$quotation = $quotation ?? [];
		$qCust = $quotation['customer'] ?? [];
		$editing = isset($edit_quotation_item) && ! empty($edit_quotation_item);
		$hasProducts = isset($article) || (! empty($quotation['products']));
		$formAction = $editing ? route('frontend.quotation.update', ['id' => $edit_quotation_item['id']]) : route('frontend.quotation.add');
		$config = [
			'csrf' => csrf_token(),
			'endpoints' => ['default' => $formAction, 'send' => route('frontend.quotation.store'), 'remove' => url('/preventivo'), 'stock' => route('frontend.product.get.variants_stock'), 'cover' => url('/variante')],
			'messages' => ['quantity' => __('frontend.quotation.quantity_error'), 'email' => __('frontend.quotation.email_error'), 'privacy' => __('frontend.quotation.privacy_error')],
			'productId' => isset($article) ? $article->product->id : null,
			'colorId' => isset($article) ? $article->color_id : null,
			'sizeId' => isset($article) ? (int) ($article->size_id ?? 0) : null,
			'stock' => isset($article) ? $article->product->color_variants_stock($article->color_id) : null,
			'quantity' => old('new_product.quantity', $quotation['new_product']['quantity'] ?? 0),
			'image' => isset($article) ? $article->cover(true) : '',
		];
		$types = \App\Models\Customer::CUSTOMER_TYPES;
		$activities = \App\Models\Customer::$activities;
	@endphp
	<div class="mx-auto max-w-7xl px-4 py-4" x-data="quotationForm(@js($config))">
		@if(isset($article))
			<x-frontend::breadcrumb :items="[['name' => config('brand.name'), 'url' => route('frontend.home')], ['name' => $article->product->name, 'url' => route('frontend.product.show.by_slug', ['slug' => $article->product->slug()])], ['name' => $article->sku.($article->color ? ' ('.$article->color->label.')' : ''), 'url' => route('frontend.product.show.by_slug.variant', ['slug' => $article->product->slug(), 'sku' => $article->sku])]]" class="mb-4 hidden md:block" />
		@endif
		<h1 class="my-6 text-3xl font-bold text-primary">{{ __('frontend.quotation.title') }}</h1>

		<form action="{{ $formAction }}" id="quotation-form" method="POST" x-ref="form" data-captcha-field="quotation_captcha" data-captcha-action="request_quotation" data-captcha-only-path="{{ parse_url(route('frontend.quotation.store'), PHP_URL_PATH) }}">
			@csrf
			@if($editing)@method('put')@endif

			<section class="rounded-card bg-primary-soft p-4 md:p-6">
				<div class="flex flex-wrap items-baseline justify-between gap-2">
					<h2 class="text-xl font-bold text-primary">{{ __('frontend.quotation.email_title') }}</h2>
					<p class="text-xs text-text-muted">{{ __('frontend.quotation.required_hint') }}</p>
				</div>
				<div class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-3">
					<x-frontend::forms.input name="customer[name]" :label="__('frontend.forms.name')" :value="$qCust['name'] ?? ''" autocomplete="given-name" />
					<x-frontend::forms.input name="customer[surname]" :label="__('frontend.forms.surname')" :value="$qCust['surname'] ?? ''" autocomplete="family-name" />
					<x-frontend::forms.input name="customer[email]" type="email" :label="__('frontend.forms.email')" :value="$qCust['email'] ?? ''" required autocomplete="email" />
					<x-frontend::forms.input name="customer[company]" :label="__('frontend.forms.company')" :value="$qCust['company'] ?? ''" autocomplete="organization" />
					<x-frontend::forms.select name="customer[customer_type]" :label="__('frontend.forms.customer_type')" :options="$types" :value="$qCust['customer_type'] ?? ''" :placeholder="__('frontend.forms.customer_type_optional')" />
					<x-frontend::forms.select name="customer[activity]" :label="__('frontend.forms.activity')" :options="$activities" :value="$qCust['activity'] ?? ''" :placeholder="__('frontend.forms.activity')" />
					<x-frontend::forms.input name="customer[phone]" type="tel" :label="__('frontend.forms.phone')" :value="$qCust['phone'] ?? ''" autocomplete="tel" />
				</div>
				@if($hasProducts)
				<div class="mt-4">
					<x-frontend::forms.consents :terms="false" :newsletter="true" :gdpr-checked="(bool) ($quotation['consent_gdpr'] ?? false)" :newsletter-checked="(bool) ($quotation['subscribe_newsletter'] ?? false)" />
					<x-frontend::captcha field="quotation_captcha" action="request_quotation" class="mt-3" />
				</div>
				@endif
			</section>

			@if(! $hasProducts)
				<p class="my-8 text-center text-xl font-bold text-primary">{{ __('frontend.quotation.empty_title') }}</p>
			@endif

			@if($editing)
			<section class="mt-6">
				<h2 class="text-xl font-bold text-primary">{{ __('frontend.quotation.edit_item') }}</h2>
				<div class="mt-3 flex flex-wrap gap-4 rounded-card border border-border-muted p-4">
					@if($edit_quotation_item['image'])<img src="{{ $edit_quotation_item['image'] }}" alt="" width="120" height="120" loading="lazy" class="h-28 w-28 object-contain">@endif
					<div class="flex-1">
						<div class="flex items-start justify-between gap-2">
							<h3 class="font-bold">{{ $edit_quotation_item['name'] }}</h3>
							<button type="button" @click="remove({{ (int) $edit_quotation_item['id'] }})" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __('frontend.quotation.delete') }}"><x-frontend::icon name="close" class="h-4 w-4" /></button>
						</div>
						<p class="text-sm text-text-muted">{{ __('frontend.product.code') }} <strong>{{ $edit_quotation_item['sku'] }}</strong></p>
						<dl class="mt-2 flex flex-wrap gap-6 text-sm">
							<div><dt class="font-semibold">{{ __('frontend.quotation.color') }}</dt><dd>{{ $edit_quotation_item['color'] }}</dd></div>
							<div><dt class="font-semibold">{{ __('frontend.quotation.size') }}</dt><dd>{{ $edit_quotation_item['size'] }}</dd></div>
							<div><x-frontend::forms.input name="update_quantity" type="number" :label="__('frontend.quotation.quantity')" :value="$edit_quotation_item['quantity']" required min="1" step="1" x-model="quantity" data-quantity /></div>
						</dl>
						<label class="mt-3 block text-sm"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.notes') }}</span><textarea name="update_notes" rows="3" class="w-full rounded border border-border bg-surface px-3 py-2" placeholder="{{ __('frontend.quotation.notes_placeholder') }}">{{ old('update_notes', $edit_quotation_item['notes']) }}</textarea></label>
					</div>
				</div>
			</section>
			@elseif(isset($article))
			<section class="mt-6">
				<h2 class="text-xl font-bold text-primary">{{ __('frontend.quotation.configure') }}</h2>
				<div class="mt-3 grid gap-4 md:grid-cols-4">
					<div class="text-center"><img :src="image" src="{{ $article->cover(true) }}" alt="{{ $article->product->name }}" width="200" height="200" class="mx-auto h-40 w-auto object-contain"></div>
					<div class="md:col-span-3">
						<input type="hidden" name="new_product[name]" value="{{ $article->product->name }}">
						<input type="hidden" name="new_product[sku]" value="{{ $article->product->sku }}">
						<input type="hidden" name="new_product[image]" :value="image" value="{{ $article->cover(true) }}">
						<h3 class="font-bold">{{ $article->product->name }}</h3>
						<p class="text-sm text-text-muted">{{ __('frontend.product.code') }} <strong>{{ $article->product->sku }}</strong></p>
						<div class="mt-3 grid gap-3 sm:grid-cols-3">
							<div>
								<label class="mb-1 block text-sm font-semibold uppercase" for="new-product-color">{{ __('frontend.quotation.color') }}</label>
								<select name="new_product[color]" id="new-product-color" @change="colorChanged($event)" class="w-full rounded border border-border bg-surface px-3 py-2 text-sm">
									@foreach($article->product->color_variants as $colorVariant)
										@if($colorVariant->color)
										<option value="{{ $colorVariant->color->label }}" data-color-article-id="{{ $colorVariant->id }}" data-color-id="{{ $colorVariant->color_id }}" @selected(old('new_product.color', $article->color?->label) == $colorVariant->color->label)>{{ $colorVariant->color->label }}</option>
										@endif
									@endforeach
								</select>
								<p class="mt-1 text-xs text-text-muted" x-text="stock === null ? '' : @js(__('frontend.quotation.stock_total', ['count' => '__N__'])).replace('__N__', stock)">{{ __('frontend.quotation.stock_total', ['count' => $config['stock']]) }}</p>
							</div>
							@if($article->product->size_variants_count() > 1)
							<div>
								<label class="mb-1 block text-sm font-semibold uppercase" for="new-product-size">{{ __('frontend.quotation.size') }}</label>
								<select name="new_product[size]" id="new-product-size" @change="sizeChanged($event)" class="w-full rounded border border-border bg-surface px-3 py-2 text-sm">
									@foreach($article->product->size_variants as $sizeVariant)
										@php $sizeLabel = $sizeVariant->size?->label ?? __('frontend.cart.one_size'); @endphp
										<option value="{{ $sizeLabel }}" data-size-id="{{ $sizeVariant->size_id }}" @selected(old('new_product.size', $quotation['new_product']['size'] ?? null) == $sizeLabel)>{{ $sizeLabel }}</option>
									@endforeach
								</select>
							</div>
							@else
								<input type="hidden" name="new_product[size]" value="{{ $article->product->size_variants()->first()?->size?->label ?? __('frontend.cart.one_size') }}">
							@endif
							<div>
								<x-frontend::forms.input name="new_product[quantity]" type="number" :label="__('frontend.quotation.quantity')" :value="$config['quantity']" required min="1" step="1" x-model="quantity" ::class="overQuantity ? 'border-danger text-danger' : ''" data-quantity />
								<div x-cloak x-show="overQuantity" class="mt-2 rounded bg-danger-soft p-2 text-xs text-danger"><strong>{{ __('frontend.quotation.over_quantity') }}</strong> {{ __('frontend.quotation.over_quantity_hint') }}</div>
							</div>
						</div>
						<fieldset class="mt-3 text-sm">
							<legend class="mb-1 font-semibold uppercase">{{ __('frontend.quotation.printing_question') }}</legend>
							<label class="mr-4 inline-flex items-center gap-1"><input type="radio" name="new_product[printing]" value="Sì" required @checked(old('new_product.printing', $quotation['new_product']['printing'] ?? null) === 'Sì') class="accent-accent">{{ __('frontend.quotation.yes') }}</label>
							<label class="inline-flex items-center gap-1"><input type="radio" name="new_product[printing]" value="No" required @checked(old('new_product.printing', $quotation['new_product']['printing'] ?? null) === 'No') class="accent-accent">{{ __('frontend.quotation.no') }}</label>
						</fieldset>
						<label class="mt-3 block text-sm"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.notes') }}</span><textarea name="new_product[notes]" rows="3" class="w-full rounded border border-border bg-surface px-3 py-2" placeholder="{{ __('frontend.quotation.notes_placeholder') }}">{{ old('new_product.notes', '') }}</textarea></label>
					</div>
				</div>
			</section>
			@endif

			@if(! empty($quotation['products']))
			<section class="mt-6">
				<h2 class="text-xl font-bold text-primary">{{ __('frontend.quotation.selected_items') }}</h2>
				<ul class="mt-3 space-y-3">
					@foreach($quotation['products'] as $item)
					<li class="flex flex-wrap gap-4 rounded-card border border-border-muted p-4">
						@if($item['image'])<img src="{{ $item['image'] }}" alt="" width="96" height="96" loading="lazy" class="h-24 w-24 object-contain">@endif
						<div class="flex-1">
							<div class="flex items-start justify-between gap-2">
								<h3 class="font-bold">{{ $item['name'] }}</h3>
								<div class="flex gap-1">
									<a href="{{ route('frontend.quotation.edit', ['id' => $item['id']]) }}" class="rounded p-1 text-text-muted hover:text-accent" aria-label="{{ __('frontend.quotation.edit') }}"><x-frontend::icon name="pencil" class="h-4 w-4" /></a>
									<button type="button" @click="remove({{ (int) $item['id'] }})" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __('frontend.quotation.delete') }}"><x-frontend::icon name="close" class="h-4 w-4" /></button>
								</div>
							</div>
							<p class="text-sm text-text-muted">{{ __('frontend.product.code') }} <strong>{{ $item['sku'] }}</strong></p>
							<dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm">
								<div><dt class="inline font-semibold">{{ __('frontend.quotation.color') }}:</dt> <dd class="inline">{{ $item['color'] }}</dd></div>
								<div><dt class="inline font-semibold">{{ __('frontend.quotation.size') }}:</dt> <dd class="inline">{{ $item['size'] }}</dd></div>
								<div><dt class="inline font-semibold">{{ __('frontend.quotation.quantity') }}:</dt> <dd class="inline">{{ $item['quantity'] }}</dd></div>
								<div><dt class="inline font-semibold">{{ __('frontend.quotation.printing') }}:</dt> <dd class="inline">{{ $item['printing'] ?? __('frontend.quotation.no') }}</dd></div>
								@if($item['notes'])<div><dt class="inline font-semibold">{{ __('frontend.quotation.notes') }}:</dt> <dd class="inline">{{ $item['notes'] }}</dd></div>@endif
							</dl>
						</div>
					</li>
					@endforeach
				</ul>
			</section>
			@endif

			<div class="my-8 flex flex-wrap justify-center gap-3">
				@if(isset($article) || $editing)
					<button type="button" @click="submit('add')" class="rounded-full border border-accent bg-surface px-6 py-3 font-bold uppercase text-accent hover:bg-accent hover:text-on-accent">{{ $editing ? __('frontend.quotation.save_and_home') : __('frontend.quotation.add_another') }}</button>
				@else
					<a href="{{ route('frontend.home') }}" class="rounded-full border border-accent bg-surface px-6 py-3 font-bold uppercase text-accent hover:bg-accent hover:text-on-accent">{{ __('frontend.quotation.add_another') }}</a>
				@endif
				@if($hasProducts && ! $editing)
					<button type="button" @click="submit('send')" class="rounded-full bg-accent px-6 py-3 font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __('frontend.quotation.send') }}</button>
				@endif
			</div>
			<div x-ref="errors">
				<div x-cloak x-show="errors.length" class="mb-4 rounded-card border border-danger bg-danger-soft p-3 text-sm text-danger" role="alert">
					<p class="font-bold" x-text="errors.length === 1 ? @js(__('frontend.forms.errors_one')) : @js(__('frontend.forms.errors_many', ['count' => '__N__'])).replace('__N__', errors.length)"></p>
					<ul class="mt-1 list-inside list-disc"><template x-for="error in errors" :key="error"><li x-text="error"></li></template></ul>
				</div>
				<x-frontend::forms.errors />
			</div>
		</form>
	</div>
@endsection
@section('endpage_js')<x-frontend::captcha-init />@endsection
