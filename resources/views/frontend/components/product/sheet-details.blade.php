{{-- @mercatura-view frontend.components.product.sheet-details @version 1 --}}
{{-- The "Dettagli prodotto" sheet: product details, the recommended decoration, packaging. Rendered inline or as a fragment. --}}
@props(['details' => [], 'defaultCustomization' => [], 'packaging' => []])
<div {{ $attributes->merge(['class' => 'grid gap-6 text-sm md:grid-cols-3']) }}>
    @foreach([['title' => __('frontend.product.details'), 'rows' => $details], ['title' => __('frontend.product.default_customization.title'), 'rows' => $defaultCustomization], ['title' => __('frontend.product.packaging'), 'rows' => $packaging]] as $block)
        @if($block['rows'])
        <div>
            <h3 class="mb-1 font-semibold">{{ $block['title'] }}</h3>
            <dl class="divide-y divide-border-muted">
                @foreach($block['rows'] as $row)
                    <div class="flex justify-between gap-4 py-1"><dt class="text-text-muted">{{ $row['label'] }}</dt><dd class="text-right font-medium">{{ $row['value'] }}</dd></div>
                @endforeach
            </dl>
        </div>
        @endif
    @endforeach
</div>
