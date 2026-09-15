{{-- @mercatura-view frontend.components.checkout.outcome @version 1 --}}
{{-- Step 4 frame: title, paragraphs (slot) and the back-home link. --}}
@props(['title', 'lastLabel' => null])
<x-frontend::checkout.page :step="4" :last-label="$lastLabel" :crumbs="[['name' => __('frontend.cart.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.checkout.title'), 'url' => route('frontend.cart.index')], ['name' => __('frontend.checkout.completed'), 'url' => url()->current()]]">
    <div class="prose max-w-none rounded-card bg-surface-muted p-4 md:p-6">
        <h1 class="text-2xl font-bold text-primary">{{ $title }}</h1>
        {{ $slot }}
        <p><a href="{{ route('frontend.home') }}" class="inline-flex items-center gap-1 font-semibold text-accent no-underline hover:underline"><x-frontend::icon name="chevron-left" class="h-4 w-4" />{{ __('frontend.checkout.back_home') }}</a></p>
    </div>
</x-frontend::checkout.page>
