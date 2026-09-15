{{-- @mercatura-view frontend.partials.field-error @version 2 --}}
{{-- Inline error under a field (kept for templates that render inputs by hand). --}}
@error($field)<p class="mt-1 text-xs text-danger" data-field-error="{{ $field }}">{{ $message }}</p>@enderror
