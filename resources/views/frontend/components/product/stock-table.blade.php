{{-- @mercatura-view frontend.components.product.stock-table @version 1 --}}
{{-- Stock per colour and size of the product (the "Disponibilità" sheet). $rows from ProductPageData::stockTable. --}}
@props(['rows' => [], 'article' => null])
@if($rows)
<div {{ $attributes->merge(['class' => 'space-y-4 text-sm']) }}>
    @foreach($rows as $color)
    <div>
        <h3 class="mb-1 flex items-center gap-2 font-semibold"><span class="inline-block h-4 w-4 rounded-full border border-border" style="{{ $color['code'] }}"></span>{{ $color['color'] }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead><tr class="border-b border-border text-left"><th class="py-1 pr-2 font-bold">{{ __('frontend.product.stock_table.sku') }}</th><th class="py-1 pr-2 font-bold">{{ __('frontend.product.stock_table.size') }}</th><th class="py-1 pr-2 font-bold">{{ __('frontend.product.stock_table.stock') }}</th><th class="py-1 font-bold">{{ __('frontend.product.stock_table.restock') }}</th></tr></thead>
                <tbody>
                @foreach($color['sizes'] as $size)
                    <tr class="border-b border-border-muted {{ $article && $article->id === $size['variant_id'] ? 'bg-primary-soft' : '' }}">
                        <td class="py-1 pr-2">{{ $size['sku'] }}</td><td class="py-1 pr-2">{{ $size['label'] }}</td><td class="py-1 pr-2">{{ $size['stock'] }}</td>
                        <td class="py-1">{{ $size['next_stock_quantity'] > 0 && $size['next_stock_date'] ? __('frontend.product.stock_table.restock_on', ['count' => $size['next_stock_quantity'], 'date' => \Carbon\Carbon::parse($size['next_stock_date'])->format('d/m/Y')]) : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
    @if($article && config('mercatura.storefront.samples') && ! $article->product->quote_only)
    <p><button type="button" data-sample-request="{{ $article->id }}" class="rounded-full border border-accent px-5 py-2 text-sm font-bold uppercase text-accent hover:bg-accent hover:text-on-accent">{{ __('frontend.product.sample_modal.request') }}</button></p>
    @endif
</div>
@else
<p class="text-sm text-text-muted">{{ __('frontend.product.stock_table.none') }}</p>
@endif
