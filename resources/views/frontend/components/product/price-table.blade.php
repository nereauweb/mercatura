{{-- @mercatura-view frontend.components.product.price-table @version 2 --}}
{{-- Neutral and printed price tables from ProductPageData::priceTable.
     Both tables share one grid: a fixed label column and the same number of equal-width
     quantity columns ($span), the shorter one padded with empty cells, so they line up. --}}
@props(['table', 'quoteUrl', 'quickQuoteId' => null])
@php
    $showPrinted = $table['printed_rows'] && $table['printed_columns'];
    $span = max(count($table['columns']), $showPrinted ? count($table['printed_columns']) : 0);
    $headLabel = $table['mode'] === 'per_size' ? __('frontend.product.size') : __('frontend.product.quantity');
@endphp
<div {{ $attributes }}>
    <p class="text-xs text-text-muted">{{ __('frontend.product.quantity_not_in_table') }} <a href="{{ $quoteUrl }}" @if($quickQuoteId) x-data @click.prevent="$dispatch('quick-quote-open', {articleId: {{ $quickQuoteId }}})" @endif class="font-semibold text-accent hover:underline">{{ __('frontend.product.calculate_quote') }}</a></p>
    @foreach([
        ['show' => (bool) $table['columns'], 'title' => __('frontend.product.price_neutral'), 'columns' => $table['columns'], 'rows' => $table['rows'], 'printed' => false],
        ['show' => $showPrinted, 'title' => __('frontend.product.price_printed'), 'columns' => $table['printed_columns'], 'rows' => $table['printed_rows'], 'printed' => true],
    ] as $section)
    @continue(! $section['show'])
    <div class="{{ $section['printed'] ? 'mt-4' : 'mt-3' }}">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-sm font-semibold">{{ $section['title'] }}</h3>
            @if($section['printed'] && $table['minCustomizationQuantity'] > 1)<p class="text-xs text-text-muted">{{ __('frontend.product.min_print_order', ['count' => $table['minCustomizationQuantity']]) }}</p>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="mt-1 w-full table-fixed border-collapse text-sm" style="min-width: calc(8rem + {{ $span }} * 5rem)">
                <colgroup><col class="w-32">@for($i = 0; $i < $span; $i++)<col>@endfor</colgroup>
                <thead><tr class="border-b border-border">
                    <th scope="col" class="py-1 pr-2 text-left font-bold">{{ $headLabel }}</th>
                    @foreach($section['columns'] as $column)<th scope="col" class="px-2 py-1 text-right font-semibold">{{ $column }}</th>@endforeach
                    @for($i = count($section['columns']); $i < $span; $i++)<td aria-hidden="true"></td>@endfor
                </tr></thead>
                <tbody>
                    @foreach($section['rows'] as $row)
                    <tr class="border-b border-border-muted"><th scope="row" class="py-1 pr-2 text-left font-bold">{{ $row['label'] }}</th>@foreach($row['cells'] as $cell)<td class="px-2 py-1 text-right">{!! $cell !!}</td>@endforeach @for($i = count($row['cells']); $i < $span; $i++)<td aria-hidden="true"></td>@endfor</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($section['printed'] && $table['printed_note'])<p class="mt-1 text-xs lowercase text-text-muted">* {{ $table['printed_note'] }}</p>@endif
    </div>
    @endforeach
</div>
