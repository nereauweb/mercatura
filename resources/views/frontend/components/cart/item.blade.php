{{-- @mercatura-view frontend.components.cart.item @version 1 --}}
{{-- One cart line: product, articles table, printings, prices, remove form. --}}
@props(['item'])
@php $product = $item['product']; $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<article {{ $attributes->merge(['class' => 'relative rounded-card border border-border-muted bg-surface p-4']) }}>
    <form method="POST" action="{{ route('frontend.cart.remove', ['id' => $item['id']]) }}" class="absolute right-2 top-2">
        @method('delete')@csrf
        <button type="submit" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __('frontend.cart.remove') }}"><x-frontend::icon name="close" class="h-4 w-4" /></button>
    </form>
    <div class="flex gap-4">
        @if($product->cover(true))
        <a href="{{ route('frontend.product.show.by_id', ['id' => $product->id]) }}" class="hidden h-24 w-24 shrink-0 items-center justify-center rounded border border-border-muted p-1 md:flex"><img src="{{ $product->cover(true) }}" alt="{{ $product->name }}" width="96" height="96" loading="lazy" class="max-h-full w-auto object-contain"></a>
        @endif
        <div class="min-w-0 flex-1">
            <h3 class="font-bold text-primary"><a href="{{ route('frontend.product.show.by_id', ['id' => $product->id]) }}">{{ $product->name }}</a></h3>
            <p class="text-sm text-text-muted">{{ $product->sku }}</p>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="bg-surface-muted text-left"><th class="px-2 py-1">{{ __('frontend.cart.article') }}</th><th class="px-2 py-1">{{ __('frontend.cart.quantity') }}</th><th class="px-2 py-1">{{ __('frontend.cart.size') }}</th><th class="px-2 py-1">{{ __('frontend.cart.color') }}</th></tr></thead>
                    <tbody>
                        @foreach($item['articles'] as $line)
                        <tr class="border-t border-border-muted"><td class="px-2 py-1">{{ $line['article']->sku }}</td><td class="px-2 py-1">{{ $line['quantity'] }}</td><td class="px-2 py-1">{{ $line['article']->size->label ?? __('frontend.cart.one_size') }}</td><td class="px-2 py-1">{{ $line['article']->color->label ?? '' }}</td></tr>
                        @endforeach
                        @if(!empty($item['printings']))
                        <tr class="bg-surface-muted"><th colspan="4" class="px-2 py-1 text-left">{{ __('frontend.cart.printing') }}</th></tr>
                        @foreach($item['printings'] as $printing)
                        <tr class="border-t border-border-muted"><td colspan="4" class="px-2 py-1">{{ $printing->printing_label() }}</td></tr>
                        @endforeach
                        @if($item['has_packaging'])<tr class="border-t border-border-muted"><td colspan="4" class="px-2 py-1">{{ __('frontend.cart.packaging') }}</td></tr>@endif
                        @endif
                    </tbody>
                </table>
            </div>
            @if(!empty($item['printings']))
            <p class="mt-2 rounded bg-danger-soft px-3 py-2 text-xs text-accent-strong"><strong>{{ __('frontend.cart.print_files_missing') }}</strong><br>{{ __('frontend.cart.print_files_hint') }}</p>
            @endif
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-text-muted"><th class="px-2 py-1 font-medium">{{ __('frontend.cart.quantity') }}</th><th class="px-2 py-1 font-medium">{{ __('frontend.cart.unit_price') }}</th><th class="px-2 py-1 font-medium">{{ __('frontend.cart.total_price') }}</th>@if($item['additional_costs'] > 0)<th class="px-2 py-1 font-medium">{{ __('frontend.cart.additional_costs') }}</th>@endif</tr></thead>
                <tbody><tr class="border-t border-border-muted font-semibold"><td class="px-2 py-1">{{ __('frontend.cart.pieces', ['count' => $item['quantity']]) }}</td><td class="px-2 py-1">€ {{ $money($item['unit_price']) }} <span class="font-normal text-text-muted">{{ __('frontend.cart.each_plus_vat') }}</span></td><td class="px-2 py-1">€ {{ $money($item['price']) }} <span class="font-normal text-text-muted">{{ __('frontend.cart.plus_vat') }}</span></td>@if($item['additional_costs'] > 0)<td class="px-2 py-1">€ {{ $money($item['additional_costs']) }}</td>@endif</tr></tbody>
            </table>
        </div>
    </div>
</article>
