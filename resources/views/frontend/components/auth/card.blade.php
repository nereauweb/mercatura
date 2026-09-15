{{-- @mercatura-view frontend.components.auth.card @version 1 --}}
{{-- Narrow centred card for auth and account forms. --}}
@props(['title', 'width' => 'max-w-md'])
<div {{ $attributes->merge(['class' => 'mx-auto '.$width.' px-4 py-10']) }}>
    <h1 class="mb-4 text-center text-2xl font-bold text-primary">{{ $title }}</h1>
    <x-frontend::forms.errors />
    {{ $slot }}
</div>
