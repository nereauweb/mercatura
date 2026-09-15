{{-- @mercatura-view frontend.components.pagination @version 1 --}}
{{-- Paginator for Livewire lists: real links for crawlers, Livewire navigation for visitors. --}}
@php
    $scrollTo = $scrollTo ?? false;
@endphp
@if ($paginator->hasPages())
<nav aria-label="{{ __('frontend.catalog.pagination') }}" class="mt-6 flex items-center justify-center gap-1 text-sm">
    @if ($paginator->onFirstPage())
        <span class="rounded px-3 py-1.5 text-text-muted" aria-disabled="true">{{ __('frontend.catalog.previous') }}</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" wire:click.prevent="previousPage('{{ $paginator->getPageName() }}')" rel="prev" class="rounded px-3 py-1.5 hover:bg-surface-muted">{{ __('frontend.catalog.previous') }}</a>
    @endif

    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="px-2 text-text-muted">{{ $element }}</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span aria-current="page" class="rounded bg-primary px-3 py-1.5 font-semibold text-on-primary">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" wire:click.prevent="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="rounded px-3 py-1.5 hover:bg-surface-muted">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" wire:click.prevent="nextPage('{{ $paginator->getPageName() }}')" rel="next" class="rounded px-3 py-1.5 hover:bg-surface-muted">{{ __('frontend.catalog.next') }}</a>
    @else
        <span class="rounded px-3 py-1.5 text-text-muted" aria-disabled="true">{{ __('frontend.catalog.next') }}</span>
    @endif
</nav>
<p class="sr-only">{{ __('frontend.catalog.page_of', ['page' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</p>
@endif
