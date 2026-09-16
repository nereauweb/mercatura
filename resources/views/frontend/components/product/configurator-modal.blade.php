{{-- @mercatura-view frontend.components.product.configurator-modal @version 1 --}}
{{-- Modal configurator (docs/04_STOREFRONT_FLOWS.md §4.2), driven by the Alpine productConfiguratorModal component
     mounted on the product page root. Five accordion steps; every amount comes from the JSON summary endpoint. --}}
@props(['product', 'article', 'configurator'])
@php $brand = config('brand'); $m = 'frontend.product.configurator_modal.'; @endphp
<div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4 md:p-8" @keydown.escape.window="close()">
    <div id="request-configurator" x-ref="root" role="dialog" aria-modal="true" :aria-label="@js(__($m.'title'))" @click.outside="close()" class="w-full max-w-4xl rounded-card bg-surface shadow-xl">
        <header class="flex items-center justify-between border-b border-border px-4 py-3">
            <h2 class="text-lg font-bold uppercase text-primary">{{ __($m.'title') }}</h2>
            <button type="button" @click="close()" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __($m.'close') }}"><x-frontend::icon name="close" /></button>
        </header>
        <div class="space-y-2 p-4">
            <p x-cloak x-show="error" x-text="error" class="rounded bg-danger-soft px-3 py-2 text-sm text-danger"></p>

            {{-- 1 · colours --}}
            <section class="rounded-card border border-border-muted">
                <button type="button" @click="step = 1" :class="step === 1 ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text'" class="flex w-full items-center justify-between px-4 py-2 text-left font-semibold">
                    <span>1. {{ __($m.'step_colors') }}</span><x-frontend::icon name="check" x-show="done[1]" class="h-5 w-5 text-positive" />
                </button>
                <div x-show="step === 1" class="p-4">
                    <ul class="flex flex-wrap gap-3">
                        @foreach($configurator['colors'] as $color)
                            <li><button type="button" @click="toggleColor({{ $color['color_id'] }})" :class="isSelected({{ $color['color_id'] }}) ? 'ring-2 ring-accent ring-offset-2' : ''" class="flex items-center gap-2 rounded-full border border-border px-3 py-1 text-sm" :aria-pressed="isSelected({{ $color['color_id'] }})"><span class="inline-block h-5 w-5 rounded-full border border-border" style="{{ $color['code'] }}"></span>{{ $color['label'] }}</button></li>
                        @endforeach
                    </ul>
                    <button type="button" @click="goToQuantities()" :disabled="selectedColors.length === 0" class="mt-4 rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __($m.'to_quantities') }}</button>
                </div>
            </section>

            {{-- 2 · quantities --}}
            <section class="rounded-card border border-border-muted" :class="done[1] ? '' : 'pointer-events-none opacity-60'">
                <button type="button" @click="if (done[1]) step = 2" :class="step === 2 ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text'" class="flex w-full items-center justify-between px-4 py-2 text-left font-semibold">
                    <span>2. {{ __($m.'step_quantities') }}</span><x-frontend::icon name="check" x-show="done[2]" class="h-5 w-5 text-positive" />
                </button>
                <div x-show="step === 2" class="space-y-4 p-4">
                    <template x-for="color in selectedColorRows()" :key="color.color_id">
                        <div class="rounded-card border border-border-muted">
                            <div class="flex items-center justify-between bg-surface-muted px-3 py-2"><span class="flex items-center gap-2 font-semibold"><span class="inline-block h-5 w-5 rounded-full border border-border" :style="color.code"></span><span x-text="color.label"></span></span><button type="button" @click="toggleColor(color.color_id)" class="text-xs text-text-muted hover:text-danger">{{ __($m.'remove') }}</button></div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead><tr class="text-left text-xs uppercase text-text-muted"><th class="px-3 py-1">{{ __('frontend.product.stock_table.sku') }}</th><th class="px-3 py-1">{{ __('frontend.product.stock_table.size') }}</th><th class="px-3 py-1">{{ __('frontend.product.stock_table.stock') }}</th><th class="px-3 py-1">{{ __('frontend.product.stock_table.restock') }}</th><th class="px-3 py-1">{{ __($m.'requested') }}</th></tr></thead>
                                    <tbody>
                                        <template x-for="size in color.sizes" :key="size.variant_id">
                                            <tr class="border-t border-border-muted">
                                                <td class="px-3 py-1" x-text="size.sku ?? ''"></td>
                                                <td class="px-3 py-1" x-text="size.label"></td>
                                                <td class="px-3 py-1" x-text="size.stock"></td>
                                                <td class="px-3 py-1" x-text="size.next_stock_quantity > 0 ? (size.next_stock_quantity + ' · ' + (size.next_stock_date ?? '')) : '-'"></td>
                                                <td class="px-3 py-1">
                                                    <input type="number" min="0" step="1" x-model.number="quantities[size.variant_id]" @input="validateQuantity(size)" :disabled="size.stock + size.next_stock_quantity === 0"
                                                           :class="quantityState(size) === 'error' ? 'border-danger' : (quantityState(size) === 'notice' ? 'border-accent' : 'border-border')" class="w-24 rounded border bg-surface px-2 py-1 text-center">
                                                    <p x-show="quantityErrors[size.variant_id]" x-text="quantityErrors[size.variant_id]" :class="quantityState(size) === 'error' ? 'text-danger' : 'text-accent'" class="mt-1 text-xs"></p>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                    <p x-cloak x-show="belowMinimumVariant()" class="rounded bg-primary-soft px-3 py-2 text-sm">{{ __('frontend.product.sample_modal.below_minimum') }} <button type="button" @click="requestSample()" class="font-bold text-accent underline">{{ __('frontend.product.sample_modal.request') }}</button></p>
                    <div class="rounded-card bg-surface-muted p-4">
                        <p class="font-semibold">{{ __($m.'decoration_question') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if($configurator['has_printing'])
                            <button type="button" @click="chooseDecoration('yes')" class="rounded-full bg-accent px-5 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __($m.'decoration_yes') }}</button>
                            @endif
                            <button type="button" @click="chooseDecoration('quote')" class="rounded-full border border-accent px-5 py-2 text-sm font-bold uppercase text-accent hover:bg-accent hover:text-on-accent">{{ __($m.'decoration_quote') }}</button>
                            <button type="button" @click="chooseDecoration('no')" class="rounded-full bg-primary-strong px-5 py-2 text-sm font-bold uppercase text-on-primary hover:bg-primary">{{ __($m.'decoration_no') }}</button>
                        </div>
                    </div>
                </div>
            </section>

            @if($configurator['has_printing'])
            {{-- 3 · printing --}}
            <section class="rounded-card border border-border-muted" :class="decoration === 'yes' ? '' : 'pointer-events-none opacity-60'">
                <button type="button" @click="if (decoration === 'yes') step = 3" :class="step === 3 ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text'" class="flex w-full items-center justify-between px-4 py-2 text-left font-semibold">
                    <span>3. {{ __($m.'step_printing') }}</span><x-frontend::icon name="check" x-show="done[3]" class="h-5 w-5 text-positive" />
                </button>
                <div x-show="step === 3" class="space-y-4 p-4">
                    <p x-show="loading && !tree" class="text-sm text-text-muted">{{ __('frontend.catalog.loading') }}</p>
                    <template x-if="tree">
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs font-semibold uppercase text-text-muted">{{ __($m.'technique') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="t in techniques()" :key="t.id">
                                        <button type="button" @click="technique = t.id; positionChoice = ''" :class="technique === t.id ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text hover:bg-surface-strong'" class="rounded-full px-4 py-1 text-sm font-semibold" x-text="t.label"></button>
                                    </template>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold uppercase text-text-muted">{{ __($m.'position') }}
                                        <select x-model="positionChoice" class="mt-1 w-full rounded border border-border bg-surface px-2 py-2 text-sm normal-case">
                                            <option value="">{{ __($m.'position_placeholder') }}</option>
                                            <template x-for="row in positionOptions()" :key="row.key"><option :value="row.key" x-text="row.text"></option></template>
                                        </select>
                                    </label>
                                    <button type="button" @click="addSelection()" :disabled="!positionChoice" class="mt-3 rounded-full border border-primary px-5 py-1 text-sm font-semibold text-primary hover:bg-primary hover:text-on-primary disabled:opacity-50">{{ __($m.'add_position') }}</button>
                                    <p class="mt-1 text-xs text-text-muted">{{ __($m.'add_position_hint') }}</p>
                                </div>
                                <div class="text-center"><template x-if="previewImage()"><img :src="previewImage()" alt="" width="160" height="160" loading="lazy" class="mx-auto h-40 w-40 object-contain"></template></div>
                            </div>
                            <ul x-show="selections.length" class="divide-y divide-border-muted rounded-card border border-border-muted text-sm">
                                <template x-for="(s, index) in selections" :key="s.key">
                                    <li class="flex items-center justify-between gap-3 px-3 py-2"><span><strong x-text="s.technique"></strong> · <span x-text="s.position"></span> · <span x-text="s.area"></span> · <span x-text="s.option"></span> · <span x-text="format(s.unit_price) + '/pz'"></span></span><button type="button" @click="removeSelection(index)" class="text-xs text-text-muted hover:text-danger">{{ __($m.'remove') }}</button></li>
                                </template>
                            </ul>
                            @if($configurator['has_packaging'])
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" :checked="packaged" @change="togglePackaging($event.target.checked)" class="accent-accent">{{ __($m.'packaging_option') }}</label>
                            @endif
                            <button type="button" @click="goToArtwork()" :disabled="selections.length === 0" class="rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __($m.'continue') }}</button>
                        </div>
                    </template>
                </div>
            </section>

            @if(config('mercatura.storefront.artwork_in_configurator'))
            {{-- 4 · artwork --}}
            <section class="rounded-card border border-border-muted" :class="done[3] && decoration === 'yes' ? '' : 'pointer-events-none opacity-60'">
                <button type="button" @click="if (done[3]) step = 4" :class="step === 4 ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text'" class="flex w-full items-center justify-between px-4 py-2 text-left font-semibold">
                    <span>4. {{ __($m.'step_artwork') }}</span><x-frontend::icon name="check" x-show="done[4]" class="h-5 w-5 text-positive" />
                </button>
                <div x-show="step === 4" class="space-y-3 p-4">
                    <p class="text-sm text-text-muted">{{ __($m.'artwork_hint') }}</p>
                    <template x-for="s in selections" :key="'a' + s.key">
                        <label class="block rounded-card border border-border-muted p-3 text-sm">
                            <span class="font-semibold" x-text="s.technique + ' · ' + s.position"></span>
                            <input type="file" accept=".jpg,.jpeg,.png,.svg,.webp,.pdf,.ai,.eps,.tif,.tiff" @change="uploadArtwork($event, s.option_id)" class="mt-2 block w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-surface-muted file:px-2 file:py-1">
                            <span x-show="artwork[s.option_id]" class="mt-1 block text-xs text-positive" x-text="artwork[s.option_id] ? '{{ __($m.'artwork_uploaded') }}: ' + artwork[s.option_id].name : ''"></span>
                        </label>
                    </template>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="goToSummary()" class="rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __($m.'continue') }}</button>
                        <button type="button" @click="goToSummary()" class="rounded-full border border-border px-6 py-2 text-sm font-semibold uppercase text-text hover:bg-surface-muted">{{ __($m.'without_artwork') }}</button>
                    </div>
                </div>
            </section>
            @endif
            @endif

            {{-- 5 · summary --}}
            <section class="rounded-card border border-border-muted" :class="done[2] ? '' : 'pointer-events-none opacity-60'">
                <button type="button" @click="if (done[2]) { step = 5; refreshSummary() }" :class="step === 5 ? 'bg-primary text-on-primary' : 'bg-surface-muted text-text'" class="flex w-full items-center justify-between px-4 py-2 text-left font-semibold">
                    <span>{{ __($m.'step_summary') }}</span>
                </button>
                <div x-show="step === 5" class="p-4" aria-live="polite">
                    <p class="font-bold text-primary">{{ $product->name }}</p>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full text-sm">
                            <tbody>
                                <template x-for="(row, index) in summaryRows()" :key="index">
                                    <tr class="border-t border-border-muted" :class="row.kind === 'article' ? '' : 'text-text-muted'">
                                        <td class="px-2 py-1">
                                            <template x-if="row.kind === 'article'"><span class="flex items-center gap-2"><span class="rounded bg-surface-muted px-2 py-0.5 font-mono text-xs" x-text="row.sku"></span><span class="inline-block h-4 w-4 rounded border border-border" :style="row.color_code"></span><span x-text="[row.color, row.size].filter(Boolean).join(' · ')"></span></span></template>
                                            <template x-if="row.kind !== 'article'"><span class="pl-4" x-text="row.label"></span></template>
                                        </td>
                                        <td class="px-2 py-1 text-right whitespace-nowrap" x-text="row.quantity ? row.quantity + ' ×' : ''"></td>
                                        <td class="px-2 py-1 text-right whitespace-nowrap" x-text="row.unit_price !== undefined ? format(row.unit_price) : ''"></td>
                                        <td class="px-2 py-1 text-right font-semibold whitespace-nowrap" x-text="row.free ? @js(__($m.'free')) : format(row.price)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <table class="mt-3 ml-auto text-sm">
                        <tbody>
                            <tr><th scope="row" class="py-1 pr-6 text-left font-normal">{{ __($m.'shipping') }}</th><td class="py-1 text-right" x-text="summary ? format(summary.shipping) : ''"></td></tr>
                            <tr><th scope="row" class="py-1 pr-6 text-left font-normal">{{ __($m.'taxable') }}</th><td class="py-1 text-right" x-text="summary ? format(summary.taxable) : ''"></td></tr>
                            <tr x-show="summary && summary.additional_costs > 0"><th scope="row" class="py-1 pr-6 text-left font-normal">{{ __('frontend.product.configurator.additional_costs') }}</th><td class="py-1 text-right" x-text="summary ? format(summary.additional_costs) : ''"></td></tr>
                            <tr><th scope="row" class="py-1 pr-6 text-left font-normal" x-text="summary ? @js(__($m.'vat')).replace(':rate', Math.round(summary.vat_rate * 100)) : ''"></th><td class="py-1 text-right" x-text="summary ? format(summary.vat) : ''"></td></tr>
                            <tr class="border-t border-border text-base"><th scope="row" class="py-1 pr-6 text-left font-bold text-primary">{{ __($m.'total') }}</th><td class="py-1 text-right font-bold" x-text="summary ? format(summary.total) : ''"></td></tr>
                            <tr><th scope="row" class="py-1 pr-6 text-left font-normal">{{ __($m.'unit_price') }}</th><td class="py-1 text-right" x-text="summary ? format(summary.unit_price) + ' + IVA' : ''"></td></tr>
                        </tbody>
                    </table>
                    <p x-show="shippingDate()" class="mt-3 rounded-card bg-primary-soft px-3 py-2 text-sm"><strong>{{ __('frontend.cart.shipping_date') }}:</strong> <span x-text="shippingDate()"></span></p>
                    <p x-cloak x-show="loading" class="mt-2 text-xs text-text-muted">{{ __('frontend.catalog.loading') }}</p>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <button type="button" @click="addToCart()" :disabled="!summary" class="rounded-full bg-accent px-8 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __('frontend.product.configurator.add_to_cart') }}</button>
                    </div>
                    @if($brand['contact']['phone'])<p class="mt-3 text-xs text-text-muted">{{ __('frontend.product.need_help') }} {{ __('frontend.product.call_us', ['phone' => '']) }} <a href="tel:{{ preg_replace('/[^\d+]/', '', $brand['contact']['phone']) }}" class="text-accent hover:underline">{{ $brand['contact']['phone'] }}</a></p>@endif
                </div>
            </section>
        </div>
        <form method="POST" action="{{ route('frontend.cart.add') }}" x-ref="cartForm" class="hidden">@csrf<input type="hidden" name="add_to_cart" value=""></form>
    </div>
</div>
