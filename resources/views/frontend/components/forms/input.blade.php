{{-- @mercatura-view frontend.components.forms.input @version 1 --}}
{{-- Labelled input with inline validation error. Slot: hint (shown under the input). --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'id' => null, 'hint' => null])
@php
    $id = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only(['class', 'x-show', 'x-cloak']) }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-semibold">{{ $label }}@if($required) <em class="text-danger not-italic">*</em>@endif</label>
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $type === 'password' ? '' : old($dot, $value) }}" @required($required)
           {{ $attributes->except(['class', 'x-show', 'x-cloak'])->merge(['class' => 'w-full rounded border bg-surface px-3 py-2 text-sm focus:border-accent focus:outline-none '.($errors->has($dot) ? 'border-danger' : 'border-border')]) }}>
    @if($hint)<p id="{{ $id }}-hint" class="mt-1 text-xs text-danger" hidden>{{ $hint }}</p>@endif
    {{ $slot }}
    @error($dot)<p class="mt-1 text-xs text-danger" data-field-error="{{ $dot }}">{{ $message }}</p>@enderror
</div>
