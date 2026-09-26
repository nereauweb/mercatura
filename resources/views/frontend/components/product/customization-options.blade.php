{{-- @mercatura-view frontend.components.product.customization-options @version 1 --}}
{{-- Every decoration of the article, one card per technique and position (the "Opzioni di personalizzazione" sheet).
     $cards from ProductPageData::customizationOptions. The supplier's position image is shown when it exists and loads;
     otherwise the same card carries a neutral placeholder (a product outline with a dashed print area), whatever the source. --}}
@props(['cards' => []])
@if($cards)
@php
    $techniques = collect($cards)->pluck('technique')->unique()->sort()->values()->all();
    $positions = collect($cards)->pluck('position')->unique()->sort()->values()->all();
@endphp
<div {{ $attributes->merge(['class' => 'text-sm']) }} x-data="{ technique: '', position: '', show(t, p) { return (this.technique === '' || this.technique === t) && (this.position === '' || this.position === p) } }">
    <p class="mb-3 text-text-muted">{{ __('frontend.product.customization_options.note') }}</p>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <button type="button" @click="technique = ''; position = ''" x-show="technique !== '' || position !== ''" x-cloak class="inline-flex items-center gap-1 text-primary hover:underline"><span aria-hidden="true">×</span> {{ __('frontend.product.customization_options.reset') }}</button>
        <select x-model="technique" class="rounded border border-border bg-surface px-2 py-1" aria-label="{{ __('frontend.product.customization_options.all_techniques') }}">
            <option value="">{{ __('frontend.product.customization_options.all_techniques') }}</option>
            @foreach($techniques as $technique)<option value="{{ $technique }}">{{ $technique }}</option>@endforeach
        </select>
        <select x-model="position" class="rounded border border-border bg-surface px-2 py-1" aria-label="{{ __('frontend.product.customization_options.all_positions') }}">
            <option value="">{{ __('frontend.product.customization_options.all_positions') }}</option>
            @foreach($positions as $position)<option value="{{ $position }}">{{ $position }}</option>@endforeach
        </select>
    </div>
    <ul class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
        @foreach($cards as $card)
        <li x-show="show(@js($card['technique']), @js($card['position']))" class="flex flex-col rounded-card border border-border bg-surface p-3">
            <p class="font-semibold text-primary">{{ $card['technique'] }}</p>
            <p class="mb-2 text-text-muted">{{ $card['position'] }}</p>
            <div class="relative mb-2 aspect-square w-full overflow-hidden rounded bg-surface-muted" x-data="{ broken: false }">
                @if($card['image'])
                <img src="{{ $card['image'] }}" alt="{{ $card['technique'] }} – {{ $card['position'] }}" width="500" height="500" loading="lazy" decoding="async" class="h-full w-full object-contain" x-show="!broken" x-on:error="broken = true">
                @endif
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center text-xs text-text-muted" @if($card['image']) x-show="broken" x-cloak @endif>
                    <svg viewBox="0 0 120 120" class="h-2/3 w-2/3" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="14" y="14" width="92" height="92" rx="12" class="opacity-40" />
                        <rect x="38" y="38" width="44" height="44" stroke-dasharray="5 4" class="text-accent" />
                    </svg>
                    <span>{{ __('frontend.product.customization_options.no_image') }}</span>
                </div>
            </div>
            <dl class="mt-auto space-y-1">
                @if($card['circle'] && $card['width_mm'])
                <div class="flex justify-between gap-2"><dt class="text-text-muted">{{ __('frontend.product.customization_options.diameter') }}</dt><dd>{{ $card['width_mm'] }} mm</dd></div>
                @elseif($card['width_mm'])
                <div class="flex justify-between gap-2"><dt class="text-text-muted">{{ __('frontend.product.customization_options.width') }}</dt><dd>{{ $card['width_mm'] }} mm</dd></div>
                @if($card['height_mm'])<div class="flex justify-between gap-2"><dt class="text-text-muted">{{ __('frontend.product.customization_options.height') }}</dt><dd>{{ $card['height_mm'] }} mm</dd></div>@endif
                @elseif($card['areas'])
                <div class="flex justify-between gap-2"><dt class="text-text-muted">{{ __('frontend.product.customization_options.area') }}</dt><dd>{{ implode(', ', $card['areas']) }}</dd></div>
                @endif
                @if($card['max_colors'] !== null)
                <div class="flex justify-between gap-2"><dt class="text-text-muted">{{ __('frontend.product.customization_options.colors') }}</dt><dd>{{ $card['colors_numeric'] ? ((int) $card['max_colors'] === 1 ? __('frontend.product.customization_options.max_colors_one') : __('frontend.product.customization_options.max_colors', ['count' => $card['max_colors']])) : ucfirst($card['max_colors']) }}</dd></div>
                @endif
                @if($card['processing_days'])
                <div class="flex justify-between gap-2"><dt class="text-text-muted">{{ __('frontend.product.customization_options.processing') }}</dt><dd>{{ __('frontend.product.customization_options.days', ['count' => $card['processing_days']]) }}</dd></div>
                @endif
            </dl>
        </li>
        @endforeach
    </ul>
</div>
@else
<p {{ $attributes->merge(['class' => 'text-sm text-text-muted']) }}>{{ __('frontend.product.customization_options.none') }}</p>
@endif
