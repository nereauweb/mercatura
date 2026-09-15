{{-- @mercatura-view frontend.components.forms.errors @version 1 --}}
{{-- Validation error summary for a bag; the bundle scrolls to it on load. --}}
@props(['bag' => 'default'])
@php $errorsForBag = $errors->{$bag} ?? $errors; @endphp
@if ($errorsForBag->any())
<div {{ $attributes->merge(['class' => 'mb-4 rounded-card border border-danger bg-danger-soft p-3 text-sm text-danger']) }} role="alert" tabindex="-1" data-form-errors-summary>
    <p class="font-bold">{{ $errorsForBag->count() === 1 ? __('frontend.forms.errors_one') : __('frontend.forms.errors_many', ['count' => $errorsForBag->count()]) }}</p>
    <ul class="mt-1 list-inside list-disc">
        @foreach ($errorsForBag->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif
