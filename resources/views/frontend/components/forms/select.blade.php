{{-- @mercatura-view frontend.components.forms.select @version 1 --}}
{{-- Labelled select. $options: list of values or value => label pairs. --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'id' => null, 'placeholder' => null])
@php
    $id = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($dot, $value);
    $isList = array_is_list($options);
@endphp
<div {{ $attributes->only(['class', 'x-show', 'x-cloak']) }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-semibold">{{ $label }}@if($required) <em class="text-danger not-italic">*</em>@endif</label>
    <select name="{{ $name }}" id="{{ $id }}" @required($required) {{ $attributes->except(['class', 'x-show', 'x-cloak'])->merge(['class' => 'w-full rounded border bg-surface px-3 py-2 text-sm focus:border-accent focus:outline-none '.($errors->has($dot) ? 'border-danger' : 'border-border')]) }}>
        @if($placeholder !== null)<option value="" @selected($current === null || $current === '')>{{ $placeholder }}</option>@endif
        @foreach($options as $key => $optionLabel)
            @php $optionValue = $isList ? $optionLabel : $key; @endphp
            <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    {{ $slot }}
    @error($dot)<p class="mt-1 text-xs text-danger" data-field-error="{{ $dot }}">{{ $message }}</p>@enderror
</div>
