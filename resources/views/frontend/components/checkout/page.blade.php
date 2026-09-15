{{-- @mercatura-view frontend.components.checkout.page @version 1 --}}
{{-- Frame shared by cart and checkout pages: breadcrumb, step bar, two columns (content + summary). --}}
@props(['step' => 1, 'cart' => null, 'isCart' => false, 'crumbs' => [], 'lastLabel' => null])
<div id="cart" class="mx-auto max-w-7xl px-4 py-4">
    @if($crumbs)
    <x-frontend::breadcrumb :items="$crumbs" class="mb-4 hidden md:block" />
    @endif
    <x-frontend::checkout.steps :current="$step" :last-label="$lastLabel" class="mb-6" />
    <div class="grid gap-6 {{ $cart ? 'md:grid-cols-4' : '' }}">
        <div class="{{ $cart ? 'md:col-span-3' : '' }}">{{ $slot }}</div>
        @if($cart)
        <div class="md:col-span-1"><div class="md:sticky md:top-4"><x-frontend::checkout.summary :cart="$cart" :is-cart="$isCart" /></div></div>
        @endif
    </div>
</div>
