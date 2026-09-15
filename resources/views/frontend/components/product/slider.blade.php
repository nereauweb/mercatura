{{-- @mercatura-view frontend.components.product.slider @version 1 --}}
{{-- Horizontal, scroll-snapping row of product cards with previous/next buttons. Pure CSS scrolling; Alpine only nudges.
     No horizontal padding on the track: a snap point away from 0 makes the browser scroll on load, and a scroll before
     first paint stops Chrome from reporting LCP at all (measured: Lighthouse NO_LCP). --}}
@props(['products'])
<div x-data="{ scrollBy(direction) { $refs.track.scrollBy({ left: direction * $refs.track.clientWidth * 0.8, behavior: 'smooth' }) } }" {{ $attributes->merge(['class' => 'relative']) }}>
    <ul x-ref="track" class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 pt-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach($products as $product)
            <li class="w-4/5 shrink-0 snap-start sm:w-[45%] md:w-[30%] lg:w-[22%]">
                <x-frontend::product.card :product="$product" />
            </li>
        @endforeach
    </ul>
    @if(count($products) > 1)
        <button type="button" @click="scrollBy(-1)" class="absolute -left-3 top-1/2 hidden -translate-y-1/2 rounded-full border border-border bg-surface p-2 text-primary shadow hover:bg-surface-muted md:block" aria-label="{{ __('frontend.home.slider_previous') }}"><x-frontend::icon name="chevron-left" /></button>
        <button type="button" @click="scrollBy(1)" class="absolute -right-3 top-1/2 hidden -translate-y-1/2 rounded-full border border-border bg-surface p-2 text-primary shadow hover:bg-surface-muted md:block" aria-label="{{ __('frontend.home.slider_next') }}"><x-frontend::icon name="chevron-right" /></button>
    @endif
</div>
