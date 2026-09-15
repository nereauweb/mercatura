{{-- @mercatura-view frontend.components.product.details @version 1 --}}
{{-- Two definition tables: product details and packaging. Rows come from ProductPageData. --}}
@props(['details' => [], 'packaging' => []])
@if($details || $packaging)
<div {{ $attributes->merge(['class' => 'grid gap-6 text-sm md:grid-cols-2']) }}>
    @if($details)
    <div>
        <h3 class="mb-1 font-semibold">{{ __('frontend.product.details') }}</h3>
        <table class="w-full border-collapse">
            @foreach($details as $row)
                <tr class="border-b border-border-muted"><th scope="row" class="py-1 pr-3 text-left font-bold">{{ $row['label'] }}</th><td class="py-1">{{ $row['value'] }}</td></tr>
            @endforeach
        </table>
    </div>
    @endif
    @if($packaging)
    <div>
        <h3 class="mb-1 font-semibold">{{ __('frontend.product.packaging') }}</h3>
        <table class="w-full border-collapse">
            @foreach($packaging as $row)
                <tr class="border-b border-border-muted"><th scope="row" class="py-1 pr-3 text-left font-bold">{{ $row['label'] }}</th><td class="py-1">{{ $row['value'] }}</td></tr>
            @endforeach
        </table>
    </div>
    @endif
</div>
@endif
