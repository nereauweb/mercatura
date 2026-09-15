{{-- @mercatura-view frontend.components.header.search @version 1 --}}
{{-- Search box with live suggestions from the core suggest endpoint (route frontend.search.suggest). --}}
@props(['id' => 'header-search'])
<div x-data="headerSearch({ endpoint: @js(route('frontend.search.suggest', ['terms' => '__TERMS__'])), debounce: 300, minLength: 2 })"
     @click.outside="close()" @keydown.escape.window="close()" {{ $attributes->merge(['class' => 'relative']) }}>
    <form role="search" class="flex" @submit.prevent="submit()" autocomplete="off">
        <label for="{{ $id }}" class="sr-only">{{ __('frontend.search.label') }}</label>
        <input id="{{ $id }}" type="search" x-model="terms" @input="onInput()" @focus="terms.length >= minLength && (open = true)"
               placeholder="{{ __('frontend.search.placeholder') }}"
               class="w-full rounded-l-full border border-border bg-surface px-4 py-2 text-sm text-text placeholder:text-text-muted focus:border-accent focus:outline-none">
        <button type="submit" class="rounded-r-full bg-accent px-4 text-on-accent hover:bg-accent-strong" aria-label="{{ __('frontend.search.submit') }}">
            <x-frontend::icon name="search" />
        </button>
    </form>
    <div x-cloak x-show="open" x-transition.opacity class="absolute left-0 right-0 top-full z-40 mt-1 max-h-[75vh] overflow-y-auto rounded-card border border-border bg-surface p-3 text-text shadow-xl">
        <div class="mb-2 flex items-center justify-between text-xs text-text-muted">
            <p class="m-0">
                <span x-show="loading">{{ __('frontend.search.searching') }}</span>
                <span x-show="!loading && error">{{ __('frontend.search.error') }}</span>
                <span x-show="!loading && !error && count !== null" x-text="count > 0 ? @js(__('frontend.search.results_count', ['count' => '__N__'])).replace('__N__', count) : @js(__('frontend.search.no_results'))"></span>
            </p>
            <button type="button" @click="close()" class="rounded p-1 hover:bg-surface-muted" aria-label="{{ __('frontend.search.close') }}"><x-frontend::icon name="close" class="h-4 w-4" /></button>
        </div>
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
            <template x-for="product in results" :key="product.id">
                <li>
                    <a :href="product.link" :title="product.name" class="block rounded-card border border-border-muted p-2 text-center hover:border-accent">
                        <img :src="product.cover" :alt="product.name" width="96" height="96" loading="lazy" class="mx-auto h-24 w-24 object-contain">
                        <span class="mt-1 block text-xs text-text-muted" x-text="product.sku"></span>
                        <span class="block text-sm font-semibold text-primary" x-text="product.name"></span>
                        <span class="block text-sm"><small>{{ __('frontend.search.from_price') }}</small> <strong x-text="'€ ' + product.price"></strong></span>
                    </a>
                </li>
            </template>
        </ul>
    </div>
</div>
