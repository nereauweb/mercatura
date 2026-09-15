{{-- @mercatura-view frontend.components.product.configurator @version 1 --}}
{{-- Print/quantity configurator: server-rendered steps driven by the Alpine productConfigurator component.
     Prices come from POST /prodotti/configuratore/articoli; sizes and colours from the personalizzazione endpoints. --}}
@props(['product', 'article', 'configurator'])
@php
    $brand = config('brand');
    $stepNumber = 1;
    $conversion = config('gtm.google_ads_id') && config('gtm.conversions.add_to_cart') ? config('gtm.google_ads_id').'/'.config('gtm.conversions.add_to_cart') : null;
@endphp
<div id="request-configurator" x-ref="root" x-cloak x-show="open" x-transition.opacity class="mt-8 rounded-card border border-border-muted bg-surface-muted p-4 md:p-6">
    <h2 class="text-xl font-bold text-primary">{{ __('frontend.product.configurator.title') }}</h2>
    <div class="mt-4 grid gap-6 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            {{-- Step 1: quantities --}}
            <section class="rounded-card bg-surface p-4">
                <button type="button" @click="step = 1" class="flex w-full items-center gap-2 text-left font-bold text-primary"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-positive text-xs text-on-dark">{{ $stepNumber++ }}</span>{{ __('frontend.product.configurator.step_quantities') }}</button>
                <div x-show="step === 1" class="mt-3">
                    <p class="text-sm font-semibold">{{ __('frontend.product.configurator.quantities_intro') }} <a href="{{ route('frontend.quotation.configure', ['id' => $article->id]) }}" class="text-accent hover:underline">{{ __('frontend.product.configurator.quantities_intro_link') }}</a>.</p>
                    <ul class="mt-3 flex flex-wrap gap-2" aria-label="{{ __('frontend.product.colors') }}">
                        @foreach($configurator['colors'] as $color)
                            <li><button type="button" @click="toggleColor({{ $color['color_id'] }})" :class="isSelected({{ $color['color_id'] }}) ? 'ring-2 ring-accent' : ''" class="block h-7 w-7 rounded-full border border-border" style="{{ $color['code'] }}" title="{{ $color['label'] }}" :aria-pressed="isSelected({{ $color['color_id'] }})"><span class="sr-only">{{ $color['label'] }}</span></button></li>
                        @endforeach
                    </ul>
                    @foreach($configurator['colors'] as $color)
                        <div x-cloak x-show="isSelected({{ $color['color_id'] }})" class="mt-3 flex flex-wrap items-start gap-4 rounded-card border border-border-muted p-3">
                            <div class="w-24 text-center">
                                <p class="text-sm font-semibold uppercase">{{ $color['label'] }}</p>
                                <span class="mt-1 inline-block h-5 w-5 rounded-full border border-border" style="{{ $color['code'] }}"></span>
                            </div>
                            <div class="flex flex-1 flex-wrap gap-4">
                                @foreach($color['sizes'] as $size)
                                    @php $v = json_encode($size); @endphp
                                    <div class="text-center text-xs">
                                        <label class="block">
                                            <span class="mb-1 block text-sm font-semibold">{{ $size['label'] }}</span>
                                            <input type="number" min="0" step="1" max="{{ $size['stock'] }}" x-model.number="quantities[{{ $size['variant_id'] }}]" @input="clampQuantity({{ $v }})" @if($size['stock'] == 0) disabled @endif
                                                   :class="quantityState({{ $v }}) === 'ok' ? 'border-border' : 'border-danger'" class="w-24 rounded border bg-surface px-2 py-1 text-center text-sm">
                                        </label>
                                        <p class="mt-1 {{ $size['stock'] == 0 ? 'text-danger' : 'text-text-muted' }}">{{ __('frontend.product.configurator.available', ['count' => $size['stock']]) }}</p>
                                        @if($size['next_stock_quantity'] > 0)<p class="text-text-muted">{{ __('frontend.product.configurator.next_stock', ['count' => $size['next_stock_quantity'], 'date' => $size['next_stock_date']]) }}</p>@endif
                                        @if($size['min_quantity'] > 1)<p class="text-text-muted">{{ __('frontend.product.configurator.min_order', ['count' => $size['min_quantity']]) }}</p>@endif
                                        <p x-cloak x-show="quantityState({{ $v }}) === 'under'" class="text-danger">{{ __('frontend.product.configurator.quantity_below_minimum') }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" @click="toggleColor({{ $color['color_id'] }})" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __('frontend.product.configurator.remove_color') }}"><x-frontend::icon name="close" /></button>
                        </div>
                    @endforeach
                    <button type="button" x-cloak x-show="canConfirm()" @click="confirmQuantities()" class="mt-4 w-full rounded-card bg-primary px-4 py-2 font-semibold text-on-primary hover:bg-primary-strong">{{ __('frontend.product.configurator.confirm_quantities') }}</button>
                </div>
            </section>

            @if($configurator['has_printing'])
            {{-- Step 2: printed or plain --}}
            <section class="rounded-card bg-surface p-4" :class="confirmed ? '' : 'pointer-events-none opacity-60'">
                <button type="button" @click="if (confirmed) step = 2" class="flex w-full items-center gap-2 text-left font-bold text-primary"><span :class="printed ? 'bg-positive' : 'bg-text-muted'" class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-on-dark">{{ $stepNumber++ }}</span>{{ __('frontend.product.configurator.step_printing') }}</button>
                <div x-cloak x-show="step === 2" class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-card border border-border-muted p-3"><input type="radio" name="has_printing" value="1" :checked="printed" @change="choosePrinted(true)" class="mt-1 accent-accent"><span><span class="block font-semibold text-primary">{{ __('frontend.product.configurator.printed_goods') }}</span><span class="text-sm text-text-muted">{{ __('frontend.product.configurator.printed_goods_text') }}</span></span></label>
                    <label class="flex cursor-pointer gap-3 rounded-card border border-border-muted p-3"><input type="radio" name="has_printing" value="0" :checked="!printed" @change="choosePrinted(false)" class="mt-1 accent-accent"><span><span class="block font-semibold text-primary">{{ __('frontend.product.configurator.plain_goods') }}</span><span class="text-sm text-text-muted">{{ __('frontend.product.configurator.plain_goods_text') }}</span></span></label>
                </div>
            </section>

            @if($configurator['has_packaging'])
            {{-- Step 3: packaging --}}
            <section x-cloak x-show="printed" class="rounded-card bg-surface p-4">
                <button type="button" @click="step = 3" class="flex w-full items-center gap-2 text-left font-bold text-primary"><span :class="packaged ? 'bg-positive' : 'bg-text-muted'" class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-on-dark">{{ $stepNumber++ }}</span>{{ __('frontend.product.configurator.step_packaging') }}</button>
                <div x-cloak x-show="step === 3" class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-card border border-border-muted p-3"><input type="radio" name="has_packaging" value="1" :checked="packaged" @change="choosePackaged(true)" class="mt-1 accent-accent"><span><span class="block font-semibold text-primary">{{ __('frontend.product.configurator.packaged') }}</span><span class="text-sm text-text-muted">{{ __('frontend.product.configurator.packaged_text') }}</span></span></label>
                    <label class="flex cursor-pointer gap-3 rounded-card border border-border-muted p-3"><input type="radio" name="has_packaging" value="0" :checked="!packaged" @change="choosePackaged(false)" class="mt-1 accent-accent"><span><span class="block font-semibold text-primary">{{ __('frontend.product.configurator.not_packaged') }}</span><span class="text-sm text-text-muted">{{ __('frontend.product.configurator.not_packaged_text') }}</span></span></label>
                </div>
            </section>
            @endif

            {{-- Step 4: positions --}}
            <section x-cloak x-show="printed" class="rounded-card bg-surface p-4">
                <button type="button" @click="step = 4" class="flex w-full items-start gap-2 text-left font-bold text-primary"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-text-muted text-xs text-on-dark">{{ $stepNumber++ }}</span><span>{{ __('frontend.product.configurator.step_positions') }}<span class="block text-xs font-normal text-text-muted">{{ __('frontend.product.configurator.positions_note') }}</span></span></button>
                <div x-cloak x-show="step === 4" class="mt-3 space-y-3">
                    @foreach($configurator['positions'] as $position)
                        <div class="rounded-card border border-border-muted p-3">
                            <h3 class="font-semibold">{{ $position['label'] }}</h3>
                            <div class="mt-2 flex flex-wrap items-center gap-4">
                                <template x-if="selections[{{ $position['id'] }}].image">
                                    <img :src="selections[{{ $position['id'] }}].image" alt="" width="120" height="120" loading="lazy" class="h-28 w-28 object-contain">
                                </template>
                                <button type="button" x-show="!selections[{{ $position['id'] }}].visible" @click="selections[{{ $position['id'] }}].visible = true" class="rounded-full border border-border p-2 text-primary hover:bg-surface-muted" aria-label="{{ __('frontend.product.configurator.add_position') }}"><x-frontend::icon name="chevron-down" /></button>
                                <div x-cloak x-show="selections[{{ $position['id'] }}].visible" class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <label class="block text-xs uppercase">{{ __('frontend.product.configurator.technique') }}
                                        <select x-model="selections[{{ $position['id'] }}].technique" @change="techniqueChanged({{ $position['id'] }})" :disabled="selections[{{ $position['id'] }}].added" class="mt-1 w-full rounded border border-border bg-surface px-2 py-1 text-sm normal-case">
                                            <option value="">{{ __('frontend.product.configurator.technique_placeholder') }}</option>
                                            @foreach($position['techniques'] as $technique)<option value="{{ $technique['id'] }}">{{ $technique['label'] }}</option>@endforeach
                                        </select></label>
                                    <label class="block text-xs uppercase">{{ __('frontend.product.configurator.size') }}
                                        <select x-model="selections[{{ $position['id'] }}].size" @change="sizeChanged({{ $position['id'] }})" :disabled="selections[{{ $position['id'] }}].added" class="mt-1 w-full rounded border border-border bg-surface px-2 py-1 text-sm normal-case">
                                            <option value="">{{ __('frontend.product.configurator.size_placeholder') }}</option>
                                            <template x-for="size in selections[{{ $position['id'] }}].sizes" :key="size.id"><option :value="size.id" x-text="size.label"></option></template>
                                        </select></label>
                                    <label class="block text-xs uppercase">{{ __('frontend.product.configurator.colours') }}
                                        <select x-model="selections[{{ $position['id'] }}].color" :disabled="selections[{{ $position['id'] }}].added" class="mt-1 w-full rounded border border-border bg-surface px-2 py-1 text-sm normal-case">
                                            <option value="">{{ __('frontend.product.configurator.colours_placeholder') }}</option>
                                            <template x-for="color in selections[{{ $position['id'] }}].colors" :key="color.id"><option :value="color.id" x-text="color.label"></option></template>
                                        </select></label>
                                    <div class="flex items-end">
                                        <button type="button" x-show="selections[{{ $position['id'] }}].color" @click="togglePrinting({{ $position['id'] }})" :class="selections[{{ $position['id'] }}].added ? 'bg-primary text-on-primary' : 'border border-primary text-primary'" class="w-full rounded px-3 py-1 text-sm font-semibold" x-text="selections[{{ $position['id'] }}].added ? @js(__('frontend.product.configurator.remove')) : @js(__('frontend.product.configurator.add'))"></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            @endif
        </div>

        {{-- Summary --}}
        <aside class="lg:sticky lg:top-4 lg:self-start">
            <div class="overflow-hidden rounded-card border border-border bg-surface text-sm" aria-live="polite">
                <h3 class="px-3 py-2 font-bold uppercase text-primary">{{ __('frontend.product.configurator.summary') }}</h3>
                <table class="w-full">
                    <tbody>
                        <template x-for="(line, index) in (summary ? summary.lines : [])" :key="index">
                            <tr class="border-t border-border-muted"><td class="px-3 py-1" :style="line.column_1_style" x-html="line.column_1"></td><td class="px-1 py-1" x-html="line.column_2"></td><td class="px-3 py-1 text-right" x-html="line.column_3"></td></tr>
                        </template>
                        <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" colspan="2" class="px-3 py-1 text-left">{{ __('frontend.product.configurator.total_quantity') }}</th><td class="px-3 py-1 text-right font-bold" x-text="summary ? summary.total_quantity : 0">0</td></tr>
                        <tr x-cloak x-show="summary && summary.total_additional_costs_amount > 0" class="border-t border-border-muted bg-surface-muted"><th scope="row" colspan="2" class="px-3 py-1 text-left">{{ __('frontend.product.configurator.additional_costs') }}</th><td class="px-3 py-1 text-right font-bold" x-html="summary ? summary.total_additional_costs : ''"></td></tr>
                        <tr class="border-t border-border-muted bg-primary-soft"><th scope="row" colspan="2" class="px-3 py-1 text-left">{{ __('frontend.product.configurator.unit_price') }}</th><td class="px-3 py-1 text-right font-bold" x-html="summary ? summary.unit_price : '0&nbsp;€'">0&nbsp;€</td></tr>
                        <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" colspan="2" class="px-3 py-1 text-left">{{ __('frontend.product.configurator.total_price') }}</th><td class="px-3 py-1 text-right font-bold" x-html="summary ? summary.total_price : '0&nbsp;€'">0&nbsp;€</td></tr>
                        <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" colspan="2" class="px-3 py-1 text-left">{{ __('frontend.product.configurator.vat') }}</th><td class="px-3 py-1 text-right font-bold" x-html="summary ? summary.total_vat : '0&nbsp;€'">0&nbsp;€</td></tr>
                        <tr class="border-t border-border bg-surface-muted"><th scope="row" colspan="2" class="px-3 py-2 text-left text-primary">{{ __('frontend.product.configurator.total_taxed') }}</th><td class="px-3 py-2 text-right font-bold" x-html="summary ? summary.total_taxed_price : '0&nbsp;€'">0&nbsp;€</td></tr>
                    </tbody>
                </table>
                <p x-cloak x-show="loading" class="px-3 py-1 text-xs text-text-muted">{{ __('frontend.catalog.loading') }}</p>
                <p x-cloak x-show="summaryError" class="px-3 py-1 text-xs text-danger">{{ __('frontend.product.configurator.summary_error') }}</p>
                <div class="grid grid-cols-2 border-t border-border">
                    <button type="button" @click="printSummary()" :disabled="!summary" class="px-3 py-2 text-center text-sm font-semibold text-primary hover:bg-surface-muted disabled:opacity-50">{{ __('frontend.product.configurator.print_summary') }}</button>
                    <button type="button" @click="addToCart()" :disabled="!summary" class="bg-accent px-3 py-2 text-center text-sm font-bold text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __('frontend.product.configurator.add_to_cart') }}</button>
                </div>
            </div>
            <form method="POST" action="{{ route('frontend.product.print_summary') }}" target="_blank" x-ref="printForm" class="hidden">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="summary_data" value=""></form>
            <form method="POST" action="{{ route('frontend.cart.add') }}" x-ref="cartForm" class="hidden">@csrf<input type="hidden" name="add_to_cart" value=""></form>
            @if($brand['contact']['phone'])
            <div class="mt-3 flex items-start gap-2 text-sm">
                <x-frontend::icon name="chat" class="mt-0.5 h-6 w-6 text-primary" />
                <div><p class="font-bold">{{ __('frontend.product.need_help') }}</p><p>{{ __('frontend.product.call_us', ['phone' => '']) }} <a href="tel:{{ preg_replace('/[^\d+]/', '', $brand['contact']['phone']) }}" class="text-accent hover:underline">{{ $brand['contact']['phone'] }}</a></p></div>
            </div>
            @endif
        </aside>
    </div>
</div>
