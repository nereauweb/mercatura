{{-- @mercatura-view frontend.components.product.gallery @version 3 --}}
{{-- Variant images: main image (LCP candidate, eager), thumbnails, full-size overlay. Fixed-height boxes: no shift. --}}
@props(['images', 'alt', 'badges' => []])
<div x-data="{ current: 0, zoom: false }" {{ $attributes->merge(['class' => 'flex flex-col gap-3 md:flex-row']) }} aria-label="{{ __('frontend.product.gallery') }}">
    @if(count($images) > 1)
    <ul class="order-2 flex gap-2 overflow-x-auto md:order-1 md:w-20 md:flex-col md:overflow-visible">
        @foreach($images as $image)
            <li>
                <button type="button" @click="current = {{ $loop->index }}" :class="current === {{ $loop->index }} ? 'border-accent' : 'border-border-muted'" class="block h-16 w-16 overflow-hidden rounded border bg-surface p-1" aria-label="{{ __('frontend.product.gallery_thumb', ['number' => $loop->iteration]) }}">
                    <img src="{{ $image['thumb'] }}" alt="" width="64" height="64" loading="lazy" class="h-full w-full object-contain">
                </button>
            </li>
        @endforeach
    </ul>
    @endif
    {{-- Definite height on every breakpoint: flex-1 only in the row layout, otherwise the box would size to the image and shift when it arrives. --}}
    <div class="relative order-1 flex h-80 shrink-0 items-center justify-center rounded-card border border-border-muted bg-surface p-3 md:order-2 md:h-96 md:flex-1">
        @forelse($images as $image)
            <button type="button" @click="zoom = true" @if(!$loop->first) x-cloak @endif x-show="current === {{ $loop->index }}" class="flex h-full w-full items-center justify-center" aria-label="{{ __('frontend.product.gallery_open') }}">
                <img src="{{ $image['web'] }}" srcset="{{ $image['web'] }} 800w, {{ $image['large'] }} 1600w" sizes="(max-width: 767px) 92vw, 28rem" alt="{{ $image['alt'] ?: $alt }}" width="800" height="800" class="max-h-full w-auto object-contain" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif>
            </button>
        @empty
            <img src="{{ config('brand.og_image') }}" alt="{{ $alt }}" width="600" height="315" class="max-h-full w-auto object-contain opacity-30">
        @endforelse
        @if($badges)
            <ul class="absolute bottom-3 right-3 flex flex-col items-end gap-1">
                @foreach($badges as $badge)
                    <li class="rounded px-2 py-0.5 text-xs font-bold uppercase {{ $badge['class'] }}">{{ $badge['label'] }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    @if(count($images))
    <div x-cloak x-show="zoom" x-transition.opacity @keydown.escape.window="zoom = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" role="dialog" aria-modal="true">
        <button type="button" @click="zoom = false" class="absolute right-4 top-4 rounded-full bg-white/20 p-2 text-white" aria-label="{{ __('frontend.product.gallery_close') }}"><x-frontend::icon name="close" /></button>
        @foreach($images as $image)
            <img x-show="current === {{ $loop->index }}" :src="'{{ $image['large'] }}'" src="" alt="{{ $alt }}" class="max-h-full max-w-full object-contain" loading="lazy">
        @endforeach
    </div>
    @endif
</div>
