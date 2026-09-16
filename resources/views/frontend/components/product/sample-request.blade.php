{{-- @mercatura-view frontend.components.product.sample-request @version 1 --}}
{{-- Sample request modal (docs/04_STOREFRONT_FLOWS.md §4.6), mounted by the product page when
     mercatura.storefront.samples is on. Colour and size selects, one plain piece priced by the
     summary endpoint (sample: 1), shipping date, add to cart as a sample line. Opened by the
     `sample-request-open` window event or a click on [data-sample-request="<variant id>"]. --}}
@props(['product', 'article', 'configurator'])
@php
    $s = 'frontend.product.sample_modal.';
    $config = [
        'articleId' => $article->id,
        'colors' => $configurator['colors'],
        'endpoints' => ['summary' => route('frontend.product.configurator.summary')],
        'csrf' => csrf_token(),
        'labels' => ['error' => __($s.'error')],
    ];
@endphp
<div x-data="sampleRequest(@js($config))" @sample-request-open.window="openWith($event.detail || {})" @keydown.escape.window="close()">
    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4 md:p-8">
        <div id="sample-request" role="dialog" aria-modal="true" aria-label="{{ __($s.'title') }}" @click.outside="close()" class="w-full max-w-lg rounded-card bg-surface shadow-xl">
            <header class="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 class="text-lg font-bold uppercase text-primary">{{ __($s.'title') }}</h2>
                <button type="button" @click="close()" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __('frontend.product.configurator_modal.close') }}"><x-frontend::icon name="close" /></button>
            </header>
            <div class="space-y-4 p-4">
                <p class="text-sm text-text-muted">{{ __($s.'intro', ['product' => $product->name]) }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.color') }}</span>
                        <select x-model="colorId" @change="colorChanged()" class="w-full rounded border border-border bg-surface px-3 py-2 text-sm">
                            @foreach($configurator['colors'] as $color)<option value="{{ $color['color_id'] }}">{{ $color['label'] }}</option>@endforeach
                        </select></label>
                    <label class="block text-sm"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.size') }}</span>
                        <select x-model="variantId" @change="sizeChanged()" class="w-full rounded border border-border bg-surface px-3 py-2 text-sm">
                            <template x-for="size in sizes()" :key="size.variant_id"><option :value="size.variant_id" x-text="size.label + ' — ' + size.sku"></option></template>
                        </select></label>
                </div>
                <p x-cloak x-show="! inStock()" class="rounded bg-danger-soft px-3 py-2 text-sm text-danger">{{ __($s.'out_of_stock') }}</p>
                <p x-cloak x-show="error" x-text="error" class="rounded bg-danger-soft px-3 py-2 text-sm text-danger"></p>
                <dl x-cloak x-show="summary" class="grid grid-cols-2 gap-y-1 rounded-card bg-surface-muted p-3 text-sm">
                    <dt>{{ __($s.'unit_price') }}</dt><dd class="text-right font-semibold" x-text="summary && format(summary.price)"></dd>
                    <dt>{{ __('frontend.product.configurator_modal.shipping') }}</dt><dd class="text-right" x-text="summary && format(summary.shipping)"></dd>
                    <dt x-text="summary ? @js(__('frontend.product.configurator_modal.vat')).replace(':rate', Math.round(summary.vat_rate * 100)) : ''"></dt><dd class="text-right" x-text="summary && format(summary.vat)"></dd>
                    <dt class="font-bold">{{ __('frontend.product.configurator_modal.total') }}</dt><dd class="text-right font-bold text-primary" x-text="summary && format(summary.total)"></dd>
                    <template x-if="shippingDate()"><dt>{{ __('frontend.cart.shipping_date') }}</dt></template>
                    <template x-if="shippingDate()"><dd class="text-right" x-text="shippingDate()"></dd></template>
                </dl>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" @click="close()" class="rounded-full border border-border px-5 py-2 text-sm font-bold uppercase hover:bg-surface-muted">{{ __('frontend.product.configurator_modal.close') }}</button>
                    <button type="button" @click="addToCart()" :disabled="loading || ! summary || ! inStock()" class="rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __($s.'add_to_cart') }}</button>
                </div>
            </div>
        </div>
    </div>
    <form method="POST" action="{{ route('frontend.cart.add') }}" x-ref="sampleForm" class="hidden">@csrf<input type="hidden" name="add_to_cart" value=""></form>
</div>
