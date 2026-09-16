{{-- @mercatura-view frontend.components.cart.table @version 1 --}}
{{-- Cart as a table (docs/04_STOREFRONT_FLOWS.md §4.7): product with articles, customizations and extras, unit price,
     quantity, subtotal, remove. $cart from CartData::build. $editable=false for the order review (no remove, no buttons). --}}
@props(['cart', 'editable' => true])
@php $money = fn ($v) => number_format((float) $v, 2, ',', '.').'&nbsp;€'; @endphp
<div {{ $attributes->merge(['class' => 'rounded-card bg-surface-muted p-4']) }}>
    @if(empty($cart['items']))
        <p class="py-6 text-center font-semibold">{{ __('frontend.cart.empty') }}</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-border text-left text-xs uppercase text-text-muted"><th class="px-2 py-2 font-semibold">{{ __('frontend.cart.table.product') }}</th><th class="px-2 py-2 text-right font-semibold">{{ __('frontend.cart.table.unit_price') }}</th><th class="px-2 py-2 text-right font-semibold">{{ __('frontend.cart.table.quantity') }}</th><th class="px-2 py-2 text-right font-semibold">{{ __('frontend.cart.table.subtotal') }}</th>@if($editable)<th class="px-2 py-2"><span class="sr-only">{{ __('frontend.cart.remove') }}</span></th>@endif</tr></thead>
            <tbody>
            @foreach($cart['items'] as $item)
                @php $product = $item['product']; $line = $item['line']; @endphp
                <tr class="border-b border-border-muted align-top">
                    <td class="px-2 py-3">
                        <div class="flex gap-3">
                            @if($product->cover(true))<img src="{{ $product->cover(true) }}" alt="{{ $product->name }}" width="64" height="64" loading="lazy" class="hidden h-16 w-16 shrink-0 rounded border border-border-muted object-contain p-1 sm:block">@endif
                            <div class="min-w-0">
                                <a href="{{ route('frontend.product.show.by_id', ['id' => $product->id]) }}" class="font-bold text-primary hover:underline">{{ $product->name }}</a>
                                <p class="text-xs text-text-muted">{{ $product->sku }}@if(!empty($item['sample'])) · <span class="font-semibold uppercase text-primary">{{ __('frontend.cart.sample') }}</span>@endif</p>
                                <ul class="mt-1 space-y-0.5 text-xs">
                                    @foreach($item['articles'] as $article)
                                        <li>{{ $article['article']->sku }} · {{ $article['article']->color->label ?? '' }} · {{ $article['article']->size->label ?? __('frontend.cart.one_size') }} · {{ __('frontend.cart.pieces', ['count' => $article['quantity']]) }}</li>
                                    @endforeach
                                    @foreach($line->customizations as $customization)
                                        <li class="text-text-muted">{{ __('frontend.cart.printing') }}: {{ $customization->label }}@if($customization->setupPrice > 0) · {{ __('frontend.product.configurator_modal.setup') }} {!! $money($customization->setupPrice) !!}@endif</li>
                                    @endforeach
                                    @if($item['has_packaging'])<li class="text-text-muted">{{ __('frontend.cart.packaging') }}</li>@endif
                                    @if($line->surcharge > 0)<li class="text-text-muted">{{ __('frontend.product.configurator_modal.surcharge', ['min' => $line->minimumQuantity]) }}: {!! $money($line->surcharge) !!}</li>@endif
                                    @if(!empty($item['shipping_date']))<li class="text-text-muted">{{ __('frontend.cart.shipping_date') }}: {{ $item['shipping_date']->format('d/m/Y') }}</li>@endif
                                </ul>
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-2 py-3 text-right">{!! $money($item['unit_price']) !!}</td>
                    <td class="whitespace-nowrap px-2 py-3 text-right">{{ $item['quantity'] }}</td>
                    <td class="whitespace-nowrap px-2 py-3 text-right font-semibold">{!! $money($item['price']) !!}</td>
                    @if($editable)
                    <td class="px-2 py-3 text-right">
                        <form method="POST" action="{{ route('frontend.cart.remove', ['id' => $item['id']]) }}">@method('delete')@csrf<button type="submit" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __('frontend.cart.remove') }}"><x-frontend::icon name="close" class="h-4 w-4" /></button></form>
                    </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($editable)
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('frontend.home') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline"><x-frontend::icon name="chevron-left" class="h-4 w-4" />{{ __('frontend.cart.continue_shopping') }}</a>
        <form method="POST" action="{{ route('frontend.cart.clear') }}" onsubmit="return window.confirm(this.dataset.confirm)" data-confirm="{{ __('frontend.cart.table.clear_confirm') }}">@csrf<button type="submit" class="rounded-card border border-border px-4 py-2 text-sm font-semibold hover:bg-surface">{{ __('frontend.cart.table.clear') }}</button></form>
    </div>
    @endif
    @endif
</div>
