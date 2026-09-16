{{-- @mercatura-view frontend.components.product.card @version 1 --}}
{{-- Product card: thumbnail, badges, name, sku, colour dots that swap the thumbnail, starting price, quote CTA.
     Slots: after-price. --}}
@props(['product'])
@php
    $cover = $product->cover(true);
    $hover = config('mercatura.storefront.card_hover_image') ? $product->main_variant()?->hoverImage() : null;
    $url = route('frontend.product.show.by_slug', ['slug' => $product->slug]);
    $badges = array_filter([
        $product->isBestseller() ? ['label' => __('frontend.product.card.badge_bestseller'), 'class' => 'bg-accent text-on-accent'] : null,
        $product->isPromo() ? ['label' => __('frontend.product.card.badge_promo'), 'class' => 'bg-danger text-on-accent'] : null,
        $product->isGreen() ? ['label' => __('frontend.product.card.badge_green'), 'class' => 'bg-positive text-on-accent'] : null,
        $product->isSale() ? ['label' => __('frontend.product.card.badge_sale'), 'class' => 'bg-danger text-on-accent'] : null,
        $product->isNew() ? ['label' => __('frontend.product.card.badge_new'), 'class' => 'bg-primary text-on-primary'] : null,
    ]);
    $colorVariants = $product->color_variants->filter(fn ($variant) => $variant->color);
    $mainVariant = $product->relationLoaded('main_variant_relationship') && $product->main_variant_relationship?->active
        ? $product->main_variant_relationship
        : $product->main_variant();
@endphp
<article x-data="{ cover: @js($cover), base: @js($cover), hover: @js($hover), async swap(id) { try { const r = await fetch(@js(url('/variante')) + '/' + id + '/cover'); if (r.ok) { this.cover = await r.text(); this.base = this.cover; this.hover = null; } } catch (e) {} } }"
         @if($hover) @mouseenter="if (hover) cover = hover" @mouseleave="cover = base" @endif
         wire:key="{{ $product->id }}"
         {{ $attributes->merge(['class' => 'group flex h-full flex-col overflow-hidden rounded-card border border-border-muted bg-surface text-center shadow-sm transition hover:shadow-md']) }}>
    <div class="relative flex h-40 items-center justify-center p-3">
        @if($cover)
            <a href="{{ $url }}" title="{{ $product->name }}">
                <img :src="cover" src="{{ $cover }}" alt="{{ $product->get_seo_title() }}" width="150" height="150" loading="lazy" decoding="async" class="mx-auto max-h-32 w-auto object-contain">
            </a>
        @endif
        @if($badges)
            <ul class="absolute bottom-2 right-2 flex flex-col items-end gap-1">
                @foreach($badges as $badge)
                    <li class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $badge['class'] }}">{{ $badge['label'] }}</li>
                @endforeach
            </ul>
        @endif
        @if($product->brand_image && file_exists(public_path('img/brands/'.$product->brand_image)))
            <img src="/img/brands/{{ $product->brand_image }}" alt="{{ $product->brand }}" width="50" height="50" loading="lazy" class="absolute right-2 top-2 h-auto max-w-[50px]">
        @endif
    </div>
    <div class="flex flex-1 flex-col px-3 py-2">
        <a href="{{ $url }}" title="{{ $product->name }}" class="block">
            <h3 class="text-sm font-bold leading-snug text-primary">{{ $product->name }}</h3>
            <p class="mt-1 text-xs text-text-muted">{{ $product->sku }}</p>
        </a>
        @if($colorVariants->isNotEmpty())
            <ul class="mt-2 flex flex-wrap justify-center gap-1" aria-label="{{ __('frontend.product.card.colors') }}">
                @foreach($colorVariants as $variant)
                    <li><a href="{{ route('frontend.product.show.by_slug.variant', ['slug' => $product->slug, 'sku' => $variant->sku]) }}" title="{{ $variant->color->label }}" @click.prevent="swap({{ $variant->id }})" class="block h-3 w-3 rounded-full border border-border" style="{{ $variant->color->render_code() }}"></a></li>
                @endforeach
            </ul>
        @endif
        <p class="mt-auto pt-2 text-sm"><small class="text-text-muted">{{ __('frontend.product.card.from_price') }}</small> <strong class="text-primary">€&nbsp;{{ $product->formatted_min_price() }}</strong></p>
        {{ $afterPrice ?? '' }}
    </div>
    <a href="{{ route('frontend.quotation.configure', ['id' => $mainVariant]) }}" class="block bg-accent py-2 text-xs font-bold uppercase text-on-accent hover:bg-accent-strong md:invisible md:group-hover:visible">{{ __('frontend.product.card.request_quote') }}</a>
</article>
