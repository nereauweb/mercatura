{{-- @mercatura-view frontend.components.header.cart-buttons @version 1 --}}
{{-- Cart and quick-quotation counters. Counts come from the session, as before. --}}
@php
    $cartCount = request()->hasSession() && session('cart') ? count(session('cart')) : 0;
    $quoteCount = request()->hasSession() && session('quotation.products') ? count(session('quotation.products')) : 0;
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center overflow-hidden rounded-full border border-white/30 text-sm']) }}>
    <a href="{{ route('frontend.cart.index') }}" title="{{ __('frontend.nav.cart') }}" class="flex items-center gap-1 px-3 py-1 {{ $cartCount > 0 ? 'bg-accent text-on-accent' : 'bg-white/10 text-on-dark' }} hover:bg-accent hover:text-on-accent">
        <span class="font-semibold">{{ $cartCount }}</span>
        <x-frontend::icon name="cart" label="{{ __('frontend.nav.cart') }}" />
    </a>
    <a href="{{ route('frontend.quotation.show') }}" title="{{ __('frontend.nav.quotation') }}" class="flex items-center gap-1 border-l border-white/30 px-3 py-1 {{ $quoteCount > 0 ? 'bg-accent text-on-accent' : 'bg-white/10 text-on-dark' }} hover:bg-accent hover:text-on-accent">
        <span class="font-semibold">{{ $quoteCount }}</span>
        <x-frontend::icon name="document" label="{{ __('frontend.nav.quotation') }}" />
    </a>
</div>
