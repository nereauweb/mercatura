{{-- @mercatura-view frontend.components.product.tabs @version 1 --}}
{{-- Lazy product tabs (docs/04_STOREFRONT_FLOWS.md §4.3): the first panel is rendered inline (the slot), the others
     fetch their HTML fragment from route frontend.product.sheet on first open. $tabs: list of [key, label, tab slug or null]. --}}
@props(['tabs', 'endpoint'])
<div {{ $attributes }} x-data="{ active: @js($tabs[0]['key']), loaded: {}, loading: null, error: null,
        async open(tab, slug) { this.active = tab; if (!slug || this.loaded[tab] !== undefined) return; this.loading = tab; this.error = null;
            try { const r = await fetch(@js($endpoint) + '/' + slug, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (!r.ok) throw new Error(r.statusText); this.loaded[tab] = await r.text(); }
            catch (e) { this.error = tab; } finally { this.loading = null; } } }">
    <div role="tablist" class="flex flex-wrap gap-1 border-b border-border">
        @foreach($tabs as $tab)
            <button type="button" role="tab" :aria-selected="active === @js($tab['key'])" @click="open(@js($tab['key']), @js($tab['slug'] ?? null))"
                    :class="active === @js($tab['key']) ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary'"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-bold uppercase">{{ $tab['label'] }}</button>
        @endforeach
    </div>
    @foreach($tabs as $tab)
        <div role="tabpanel" x-show="active === @js($tab['key'])" x-cloak class="rounded-b-card bg-surface-muted p-4">
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
