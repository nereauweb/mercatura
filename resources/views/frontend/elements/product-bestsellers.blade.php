{{-- @mercatura-view frontend.elements.product-bestsellers @version 2 --}}
{{-- Response of POST /prodotti/bestsellers (kept for custom scripts); the product page renders related products directly. --}}
@if (isset($bestsellers) && $bestsellers && $bestsellers->count() > 0)
<section class="mt-10">
	<h2 class="mb-3 text-center text-xl font-bold uppercase text-primary">{{ __('frontend.product.related') }}</h2>
	<x-frontend::product.slider :products="$bestsellers" />
</section>
@endif
