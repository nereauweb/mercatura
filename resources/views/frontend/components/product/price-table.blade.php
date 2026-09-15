{{-- @mercatura-view frontend.components.product.price-table @version 1 --}}
{{-- Neutral and printed price tables from ProductPageData::priceTable. --}}
@props(['table', 'quoteUrl'])
<div {{ $attributes }}>
    <p class="text-xs text-text-muted">{{ __('frontend.product.quantity_not_in_table') }} <a href="{{ $quoteUrl }}" class="font-semibold text-accent hover:underline">{{ __('frontend.product.calculate_quote') }}</a></p>
    @if($table['columns'])
    <div class="mt-3">
        <h3 class="text-sm font-semibold">{{ __('frontend.product.price_neutral') }}</h3>
        <div class="overflow-x-auto">
            <table class="mt-1 w-full border-collapse text-sm">
                <thead><tr class="border-b border-border">
                    <th scope="col" class="py-1 pr-2 text-left font-bold">{{ $table['mode'] === 'per_size' ? __('frontend.product.size') : __('frontend.product.quantity') }}</th>
                    @foreach($table['columns'] as $column)<th scope="col" class="px-2 py-1 text-right font-semibold">{{ $column }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @foreach($table['rows'] as $row)
                    <tr class="border-b border-border-muted"><th scope="row" class="py-1 pr-2 text-left font-bold">{{ $row['label'] }}</th>@foreach($row['cells'] as $cell)<td class="px-2 py-1 text-right">{!! $cell !!}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @if($table['printed_rows'] && $table['printed_columns'])
    <div class="mt-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-sm font-semibold">{{ __('frontend.product.price_printed') }}</h3>
            @if($table['min_print_quantity'] > 1)<p class="text-xs text-text-muted">{{ __('frontend.product.min_print_order', ['count' => $table['min_print_quantity']]) }}</p>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="mt-1 w-full border-collapse text-sm">
                <thead><tr class="border-b border-border">
                    <th scope="col" class="py-1 pr-2 text-left font-bold">{{ $table['mode'] === 'per_size' ? __('frontend.product.size') : __('frontend.product.quantity') }}</th>
                    @foreach($table['printed_columns'] as $column)<th scope="col" class="px-2 py-1 text-right font-semibold">{{ $column }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @foreach($table['printed_rows'] as $row)
                    <tr class="border-b border-border-muted"><th scope="row" class="py-1 pr-2 text-left font-bold">{{ $row['label'] }}</th>@foreach($row['cells'] as $cell)<td class="px-2 py-1 text-right">{!! $cell !!}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($table['printed_note'])<p class="mt-1 text-xs lowercase text-text-muted">* {{ $table['printed_note'] }}</p>@endif
    </div>
    @endif
</div>
