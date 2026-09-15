{{-- @mercatura-view frontend.pages.checkout.summary @version 2 --}}
{{-- Kept as an include for compatibility; the markup is the checkout.summary component. --}}
<x-frontend::checkout.summary :cart="$cart" :is-cart="$is_cart ?? false" />
