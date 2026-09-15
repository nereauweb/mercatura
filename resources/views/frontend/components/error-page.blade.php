{{-- @mercatura-view frontend.components.error-page @version 1 --}}
{{-- HTTP error page body: status, title, description, suggestion, links home and to contacts. --}}
@props(['status'])
@php $copy = __('frontend.errors.'.$status); $copy = is_array($copy) ? $copy : __('frontend.errors.500'); @endphp
<div class="mx-auto max-w-3xl px-4 py-16 text-center">
    <p class="text-6xl font-bold leading-none text-primary">{{ $status }}</p>
    <h1 class="mt-2 text-2xl font-bold uppercase text-primary">{{ $copy['title'] }}</h1>
    <p class="mt-6 text-lg text-primary">{{ $copy['description'] }}</p>
    <p class="mt-2 text-text-muted">{{ $copy['suggestion'] }}</p>
    <p class="mt-8"><a href="{{ route('frontend.home') }}" class="inline-flex items-center gap-2 rounded-full bg-positive px-8 py-3 font-bold text-on-dark shadow hover:opacity-90"><x-frontend::icon name="home" />{{ __('frontend.errors.back_home') }}</a></p>
    <p class="mt-4"><a href="{{ route('frontend.contacts.index') }}" class="inline-flex items-center gap-2 text-sm text-primary hover:underline"><x-frontend::icon name="envelope" class="h-4 w-4" />{{ __('frontend.errors.contact_us') }}</a></p>
</div>
