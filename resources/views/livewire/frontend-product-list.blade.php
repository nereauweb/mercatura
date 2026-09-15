{{-- @mercatura-view livewire.frontend-product-list @version 2 --}}
{{-- Filters panel + product grid + pagination. Filters apply immediately; wire:loading gives visual state
     while the smallest component (this one) re-renders. --}}
@php
    $options = $this->options;
    $navigation = $this->navigation;
    $categoryModel = $this->category;
    $priceMin = $options['price'][0];
    $priceMax = $options['price'][1];
    $colorOptions = $this->visibleOptions('colors', $options['colors'], $colors, 'id');
    $brandOptions = $this->visibleOptions('brands', $options['brands'], $brands, 'name');
    $printOptions = $this->visibleOptions('prints', $options['prints'], $print_techniques, 'label');
@endphp
<div class="grid gap-6 md:grid-cols-4" x-data="{ showNav: false, showFilters: false }">
	<aside class="md:col-span-1">
		<div class="mb-3 grid grid-cols-2 gap-2 md:hidden">
			<button type="button" @click="showNav = !showNav" :aria-expanded="showNav" class="flex items-center justify-center gap-2 rounded-card border border-border px-3 py-2 text-sm font-semibold"><x-frontend::icon name="menu" class="h-4 w-4" />{{ $categoryModel ? __('frontend.catalog.subcategories') : __('frontend.catalog.categories') }}</button>
			<button type="button" @click="showFilters = !showFilters" :aria-expanded="showFilters" class="flex items-center justify-center gap-2 rounded-card border border-border px-3 py-2 text-sm font-semibold"><x-frontend::icon name="bolt" class="h-4 w-4" />{{ __('frontend.catalog.filters') }} @if($activeFilters)<span class="rounded-full bg-accent px-1.5 text-xs text-on-accent">{{ $activeFilters }}</span>@endif</button>
		</div>

		<div class="md:sticky md:top-4 md:space-y-4">
			@if(count($navigation))
			<nav x-cloak x-show="showNav" class="overflow-hidden rounded-card border border-border-muted bg-surface-muted md:!block" aria-label="{{ $categoryModel ? __('frontend.catalog.subcategories') : __('frontend.catalog.categories') }}" wire:ignore>
				<div class="flex items-center gap-2 bg-positive px-3 py-2 text-on-dark">
					@if($categoryModel && $categoryModel->icon_rev)
						<img src="/storage/categories/icons/{{ $categoryModel->icon_rev }}" alt="" width="28" height="28" loading="lazy" class="h-7 w-7 object-contain">
					@endif
					<h2 class="text-base font-bold uppercase">{{ $categoryModel ? $categoryModel->name : __('frontend.catalog.all_products') }}</h2>
				</div>
				<ul class="px-3 py-2 text-sm">
					@foreach($navigation as $item)
						<li class="flex items-center justify-between gap-2 py-1 {{ $categoryModel && $item['id'] === $categoryModel->id ? 'font-semibold text-accent' : '' }}">
							<a href="{{ route('frontend.category.show.by_slug', ['slug' => $item['slug']]) }}" class="hover:text-accent">{{ $item['name'] }}</a>
							<span class="text-xs text-text-muted">({{ $item['count'] }})</span>
						</li>
					@endforeach
				</ul>
			</nav>
			@endif

			<form x-cloak x-show="showFilters" @submit.prevent class="space-y-4 rounded-card border border-border-muted bg-surface-muted p-4 text-sm md:!block" aria-label="{{ __('frontend.catalog.filters') }}">
				<div class="flex items-center justify-between">
					<h2 class="font-bold uppercase text-primary-strong">{{ __('frontend.catalog.filters') }}</h2>
					@if($activeFilters)
						<button type="button" wire:click="resetFilters" class="text-xs text-accent hover:underline">{{ __('frontend.catalog.reset_filters') }}</button>
					@endif
				</div>

				<fieldset>
					<legend class="mb-1 font-semibold">{{ __('frontend.catalog.price') }}</legend>
					<div class="grid grid-cols-2 gap-2">
						<label class="block"><span class="sr-only">{{ __('frontend.catalog.price_min') }}</span>
							<input type="number" wire:model.live.debounce.600ms="priceMinInput" min="{{ $priceMin }}" max="{{ $priceMax }}" step="0.01" placeholder="{{ __('frontend.catalog.price_min') }}" class="w-full rounded border border-border bg-surface px-2 py-1"></label>
						<label class="block"><span class="sr-only">{{ __('frontend.catalog.price_max') }}</span>
							<input type="number" wire:model.live.debounce.600ms="priceMaxInput" min="{{ $priceMin }}" max="{{ $priceMax }}" step="0.01" placeholder="{{ __('frontend.catalog.price_max') }}" class="w-full rounded border border-border bg-surface px-2 py-1"></label>
					</div>
					<p class="mt-1 text-xs text-text-muted">€ {{ number_format($priceMin, 2, ',', '.') }} – € {{ number_format($priceMax, 2, ',', '.') }}</p>
				</fieldset>

				@if($this->purchaseOptions !== [])
				<label class="block">
					<span class="mb-1 block font-semibold">{{ __('frontend.catalog.purchase_type') }}</span>
					<select wire:model.live="urlPurchase" class="w-full rounded border border-border bg-surface px-2 py-1">
						<option value="">{{ __('frontend.catalog.purchase_any') }}</option>
						@foreach($this->purchaseOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
					</select>
				</label>
				@endif

				<fieldset class="space-y-1">
					<label class="flex items-center gap-2"><input type="checkbox" wire:model.live="is_new" class="accent-accent">{{ __('frontend.catalog.only_new') }}</label>
					<label class="flex items-center gap-2"><input type="checkbox" wire:model.live="is_sale" class="accent-accent">{{ __('frontend.catalog.only_sale') }}</label>
					<label class="flex items-center gap-2"><input type="checkbox" wire:model.live="is_green" class="accent-accent">{{ __('frontend.catalog.only_green') }}</label>
					<label class="flex items-center gap-2"><input type="checkbox" wire:model.live="is_promo" class="accent-accent">{{ __('frontend.catalog.only_promo') }}</label>
				</fieldset>

				@if(count($options['colors']))
				<details class="group" open>
					<summary class="cursor-pointer font-semibold">{{ __('frontend.catalog.colors') }} <span class="text-xs text-text-muted">({{ count($options['colors']) }})</span></summary>
					<ul class="mt-2 max-h-56 space-y-1 overflow-y-auto pr-1">
						@foreach($colorOptions['items'] as $color)
							<li><label class="flex items-center gap-2"><input type="checkbox" wire:model.live="colors" value="{{ $color['id'] }}" class="accent-accent"><span class="inline-block h-3.5 w-3.5 rounded-full border border-border" style="{{ $color['code'] }}"></span>{{ $color['label'] }}</label></li>
						@endforeach
					</ul>
					@if($colorOptions['hidden'])
						<button type="button" wire:click="expand('colors')" class="mt-1 text-xs text-accent hover:underline">{{ __('frontend.catalog.show_all', ['count' => $colorOptions['hidden']]) }}</button>
					@endif
				</details>
				@endif

				@if(count($options['brands']))
				<details class="group">
					<summary class="cursor-pointer font-semibold">{{ __('frontend.catalog.brands') }} <span class="text-xs text-text-muted">({{ count($options['brands']) }})</span></summary>
					<ul class="mt-2 max-h-56 space-y-1 overflow-y-auto pr-1">
						@foreach($brandOptions['items'] as $brandOption)
							<li><label class="flex items-center gap-2"><input type="checkbox" wire:model.live="brands" value="{{ $brandOption['name'] }}" class="accent-accent">{{ $brandOption['name'] }} <span class="text-xs text-text-muted">({{ $brandOption['count'] }})</span></label></li>
						@endforeach
					</ul>
					@if($brandOptions['hidden'])
						<button type="button" wire:click="expand('brands')" class="mt-1 text-xs text-accent hover:underline">{{ __('frontend.catalog.show_all', ['count' => $brandOptions['hidden']]) }}</button>
					@endif
				</details>
				@endif

				@if(count($options['prints']))
				<details class="group">
					<summary class="cursor-pointer font-semibold">{{ __('frontend.catalog.print_techniques') }} <span class="text-xs text-text-muted">({{ count($options['prints']) }})</span></summary>
					<ul class="mt-2 max-h-56 space-y-1 overflow-y-auto pr-1">
						@foreach($printOptions['items'] as $print)
							<li><label class="flex items-center gap-2"><input type="checkbox" wire:model.live="print_techniques" value="{{ $print['label'] }}" class="accent-accent">{{ $print['label'] }} <span class="text-xs text-text-muted">({{ $print['count'] }})</span></label></li>
						@endforeach
					</ul>
					@if($printOptions['hidden'])
						<button type="button" wire:click="expand('prints')" class="mt-1 text-xs text-accent hover:underline">{{ __('frontend.catalog.show_all', ['count' => $printOptions['hidden']]) }}</button>
					@endif
				</details>
				@endif
			</form>
		</div>
	</aside>

	<section class="relative md:col-span-3" aria-live="polite" aria-busy="false" wire:loading.attr="aria-busy">
		<div wire:loading.delay class="absolute inset-0 z-10 flex items-start justify-center rounded-card bg-surface/70 pt-10 text-sm font-semibold text-primary">
			{{ __('frontend.catalog.loading') }}
		</div>
		<div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-sm">
			<p>{{ __('frontend.catalog.total_products', ['count' => $count]) }}</p>
			<div class="flex items-center gap-4">
				<label class="flex items-center gap-2">{{ __('frontend.catalog.sort_by') }}
					<select wire:model.live="urlSort" class="rounded border border-border bg-surface px-2 py-1">
						<option value="price">{{ __('frontend.catalog.sort_price') }}</option>
						<option value="name">{{ __('frontend.catalog.sort_name') }}</option>
						<option value="position">{{ __('frontend.catalog.sort_position') }}</option>
					</select>
				</label>
				<label class="flex items-center gap-2">{{ __('frontend.catalog.show') }}
					<select wire:model.live="limit" class="rounded border border-border bg-surface px-2 py-1">
						<option value="16">16</option>
						<option value="24">24</option>
						<option value="36">36</option>
					</select>
				</label>
			</div>
		</div>

		@if($products->count())
			<ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4" wire:loading.class="opacity-60">
				@foreach($products as $product)
					<li wire:key="product-{{ $product->id }}"><x-frontend::product.card :product="$product" /></li>
				@endforeach
			</ul>
		@else
			<p class="rounded-card border border-border-muted p-6 text-center text-text-muted">{{ __('frontend.catalog.no_products') }}</p>
		@endif

		{{ $products->links('frontend.components.pagination') }}
	</section>
</div>
