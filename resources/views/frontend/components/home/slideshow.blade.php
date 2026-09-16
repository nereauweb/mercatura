{{-- @mercatura-view frontend.components.home.slideshow @version 1 --}}
{{-- Hero slideshow from content_home_slides. The first slide is server-rendered and is the LCP candidate;
     the others are hidden until Alpine takes over. Fixed height: nothing shifts. --}}
@props(['slides'])
@php
    // Colours are token names from the legacy content ('bianco', 'blu-corporate'…) or a hex value from the admin colour picker.
    $textTokens = ['bianco' => 'text-on-dark', 'blu-corporate' => 'text-primary', 'nero' => 'text-text', 'verde' => 'text-positive', 'arancione' => 'text-accent'];
    $token = fn ($name) => $textTokens[$name] ?? (str_starts_with((string) $name, '#') ? '' : 'text-text');
    $inline = fn ($name) => str_starts_with((string) $name, '#') ? 'color: '.e($name) : '';
@endphp
@if(count($slides))
<section x-data="slideshow({ count: {{ count($slides) }} })" class="relative h-[380px] overflow-hidden rounded-card md:h-[420px]" aria-roledescription="carousel" aria-label="{{ __('frontend.home.slideshow_label') }}">
    @foreach($slides as $slide)
        <div @if(!$loop->first) x-cloak @endif x-show="index === {{ $loop->index }}" x-transition.opacity.duration.500ms class="absolute inset-0" style="background-color: {{ $slide->background_color }}" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ count($slides) }}">
            @if($slide->background_image)
                <picture>
                    @if($slide->mobile_image)<source media="(max-width: 639px)" srcset="/storage/home_slides/{{ $slide->mobile_image }}">@endif
                    <img src="/storage/home_slides/{{ $slide->background_image }}" alt="{{ trim(strip_tags($slide->title_text.' '.$slide->subtitle_text)) }}" width="1200" height="420" class="{{ $slide->mobile_image ? '' : 'hidden sm:block' }} h-full w-full object-cover" @if($loop->first) fetchpriority="high" decoding="sync" @else loading="lazy" decoding="async" @endif>
                </picture>
            @endif
            <div class="absolute left-0 top-0 max-w-lg p-6 md:p-8">
                <p class="text-xl font-bold uppercase leading-tight {{ $token($slide->subtitle_color) }}" style="{{ $inline($slide->subtitle_color) }}">{!! $slide->subtitle_text !!}</p>
                <p class="mt-1 text-3xl font-bold uppercase leading-tight md:text-4xl {{ $token($slide->title_color) }}" style="{{ $inline($slide->title_color) }}">{!! $slide->title_text !!}</p>
                @if($slide->text)<p class="mt-2 text-base font-semibold {{ $token($slide->text_color) }}" style="{{ $inline($slide->text_color) }}">{!! $slide->text !!}</p>@endif
            </div>
            @if($slide->cta_text && $slide->cta_link)
                <a href="{{ $slide->cta_link }}" class="absolute bottom-8 left-1/2 -translate-x-1/2 rounded-full bg-accent px-6 py-3 text-base font-bold text-on-accent shadow hover:bg-accent-strong md:left-8 md:translate-x-0">{!! $slide->cta_text !!}</a>
            @endif
        </div>
    @endforeach
    @if(count($slides) > 1)
        <button type="button" @click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/30 p-2 text-white hover:bg-black/50" aria-label="{{ __('frontend.home.slide_previous') }}"><x-frontend::icon name="chevron-left" /></button>
        <button type="button" @click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/30 p-2 text-white hover:bg-black/50" aria-label="{{ __('frontend.home.slide_next') }}"><x-frontend::icon name="chevron-right" /></button>
        <ul class="absolute bottom-2 left-1/2 flex -translate-x-1/2 gap-2">
            @foreach($slides as $slide)
                <li><button type="button" @click="go({{ $loop->index }})" :class="index === {{ $loop->index }} ? 'bg-white' : 'bg-white/40'" class="block h-2.5 w-2.5 rounded-full" aria-label="{{ __('frontend.home.slide_go_to', ['number' => $loop->iteration]) }}"></button></li>
            @endforeach
        </ul>
    @endif
</section>
@endif
