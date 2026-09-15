{{-- @mercatura-view frontend.components.breadcrumb @version 1 --}}
{{-- Breadcrumb trail plus its BreadcrumbList JSON-LD. $items: list of ['name' => …, 'url' => …], last one is the current page. --}}
@props(['items'])
<nav aria-label="{{ __('frontend.catalog.breadcrumb') }}" {{ $attributes->merge(['class' => 'text-sm text-text-muted']) }}>
    <ol class="flex flex-wrap items-center gap-1">
        @foreach($items as $item)
            <li class="flex items-center gap-1">
                @if($loop->last)
                    <span aria-current="page" class="font-semibold text-text">{{ $item['name'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" class="hover:text-accent">{{ $item['name'] }}</a>
                    <x-frontend::icon name="chevron-right" class="h-3 w-3" />
                @endif
            </li>
        @endforeach
    </ol>
</nav>
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($items)->map(fn ($item, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']])->values()->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
