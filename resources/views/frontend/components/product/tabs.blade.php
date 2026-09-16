{{-- @mercatura-view frontend.components.product.tabs @version 1 --}}
{{-- Lazy product tabs (docs/04_STOREFRONT_FLOWS.md §4.3): the first panel is rendered inline (the slot), the others
     fetch their HTML fragment from route frontend.product.sheet: on first open, or in the browser's idle time after
     the page has loaded when $prefetch is true (default), so a click is instant and the first paint pays nothing.
     $tabs: list of [key, label, tab slug or null]. Any element may open a tab: $dispatch('open-product-tab', 'stock')
     switches to it and scrolls the tabs into view. --}}
@props(['tabs', 'endpoint', 'prefetch' => true])
<div {{ $attributes }} @open-product-tab.window="if (slugs[$event.detail] !== undefined) { open($event.detail, slugs[$event.detail]); $el.scrollIntoView({ behavior: 'smooth', block: 'start' }) }"
     x-data="{ active: @js($tabs[0]['key']), slugs: @js(array_column($tabs, 'slug', 'key')), loaded: {}, pending: {}, loading: null, error: null,
        init() { if (! @js((bool) $prefetch)) return; const idle = window.requestIdleCallback || ((f) => setTimeout(f, 1500));
            window.addEventListener('load', () => idle(() => @js(array_values(array_filter($tabs, fn ($t) => ! empty($t['slug'])))).forEach((t) => this.load(t.key, t.slug))), { once: true }); },
        open(tab, slug) { this.active = tab; if (slug && this.loaded[tab] === undefined) this.loading = tab; return this.load(tab, slug); },
        async load(tab, slug) { if (!slug || this.loaded[tab] !== undefined || this.pending[tab]) return; this.pending[tab] = true; this.error = null;
            try { const r = await fetch(@js($endpoint) + '/' + slug, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (!r.ok) throw new Error(r.statusText); this.loaded[tab] = await r.text(); }
            catch (e) { this.error = tab; } finally { this.pending[tab] = false; if (this.loading === tab) this.loading = null; } } }">
    <div role="tablist" class="flex flex-wrap gap-1 border-b border-border">
        @foreach($tabs as $tab)
            <button type="button" role="tab" :aria-selected="active === @js($tab['key'])" @click="open(@js($tab['key']), @js($tab['slug'] ?? null))"
                    :class="active === @js($tab['key']) ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary'"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-bold uppercase">{{ $tab['label'] }}</button>
        @endforeach
    </div>
    @foreach($tabs as $tab)
        {{-- The first panel is visible before Alpine starts (no x-cloak): no layout shift when the script arrives. --}}
        <div role="tabpanel" x-show="active === @js($tab['key'])" @if(! $loop->first) x-cloak @endif class="rounded-b-card bg-surface-muted p-4">
            @if($loop->first)
                {{ $slot }}
            @else
                <p x-show="loading === @js($tab['key'])" class="text-sm text-text-muted">{{ __('frontend.product.tab_loading') }}</p>
                <p x-show="error === @js($tab['key'])" class="text-sm text-danger">{{ __('frontend.product.tab_error') }}</p>
                <div x-html="loaded[@js($tab['key'])] ?? ''"></div>
            @endif
        </div>
    @endforeach
</div>
