{{-- @mercatura-view frontend.components.home.product-slider @version 2 --}}
{{-- Kept as an alias of product.slider for the home page; override product.slider to restyle both. --}}
@props(['products'])
<x-frontend::product.slider :products="$products" {{ $attributes }} />
