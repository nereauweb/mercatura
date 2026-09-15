{{-- @mercatura-view frontend.components.forms.checkbox @version 1 --}}
{{-- Checkbox with its label as the slot. --}}
@props(['name', 'value' => '1', 'checked' => false, 'required' => false, 'id' => null])
@php
    $id = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->merge(['class' => 'text-sm']) }}>
    <label class="flex items-start gap-2">
        <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked(old($dot, $checked)) @required($required) class="mt-0.5 accent-accent">
        <span>{{ $slot }}@if($required) <em class="text-danger not-italic">*</em>@endif</span>
    </label>
    @error($dot)<p class="mt-1 text-xs text-danger" data-field-error="{{ $dot }}">{{ $message }}</p>@enderror
</div>
