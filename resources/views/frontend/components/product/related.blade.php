{{-- @mercatura-view frontend.components.product.related @version 1 --}}
{{-- "Ti potrebbero interessare anche", loaded after the page: the first response carries the heading and a box as tall
     as a row of cards; the cards (frontend.components.product.slider) come from route frontend.product.sheet, tab
     "correlati", in the browser's idle time after load. Render it only when $page['related'] is not empty. --}}
@props(['endpoint', 'title' => null])
<section {{ $attributes->merge(['class' => 'mt-10']) }} x-show="! failed"
         x-data="{ html: '', failed: false,
            init() { const idle = window.requestIdleCallback || ((f) => setTimeout(f, 1500));
                const run = () => idle(async () => { try { const r = await fetch(@js($endpoint), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (! r.ok) throw new Error(r.statusText); this.html = await r.text(); } catch (e) { this.failed = true; } });
                document.readyState === 'complete' ? run() : window.addEventListener('load', run, { once: true }); } }">
    <h2 class="mb-3 text-center text-xl font-bold uppercase text-primary">{{ $title ?? __('frontend.product.related') }}</h2>
    <div class="min-h-80" x-html="html"></div>
</section>
