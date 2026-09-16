/**
 * Sample request (docs/04_STOREFRONT_FLOWS.md §4.6): colour and size of one
 * plain piece, priced by the JSON summary endpoint with sample: 1 (tier 1,
 * article markup, no minimum surcharge), added to the cart as a sample line.
 * Opens on a `sample-request-open` window event ({ variantId? }) and on any
 * click on [data-sample-request="<variant id>"], so buttons inside lazily
 * loaded fragments (the Disponibilità tab) work without Alpine wiring.
 */
export function sampleRequest(config) {
    const money = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });

    return {
        open: false,
        loading: false,
        error: null,
        colors: config.colors ?? [],
        colorId: null,
        variantId: null,
        summary: null,

        init() {
            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-sample-request]');
                if (!button) return;
                event.preventDefault();
                this.openWith({ variantId: parseInt(button.dataset.sampleRequest, 10) || null });
            });
        },

        format(value) {
            return money.format(value ?? 0);
        },

        async openWith(detail) {
            const variantId = detail?.variantId ?? config.articleId;
            const color = this.colors.find((c) => c.sizes.some((s) => s.variant_id === variantId)) ?? this.colors[0];
            this.colorId = color?.color_id ?? null;
            this.variantId = color?.sizes.some((s) => s.variant_id === variantId) ? variantId : (color?.sizes[0]?.variant_id ?? null);
            this.open = true;
            this.error = null;
            document.body.classList.add('overflow-hidden');
            await this.refresh();
        },

        close() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
        },

        sizes() {
            return this.colors.find((c) => c.color_id === this.colorId)?.sizes ?? [];
        },

        async colorChanged() {
            this.colorId = parseInt(this.colorId, 10);
            this.variantId = this.sizes()[0]?.variant_id ?? null;
            await this.refresh();
        },

        async sizeChanged() {
            this.variantId = parseInt(this.variantId, 10);
            await this.refresh();
        },

        inStock() {
            const size = this.sizes().find((s) => s.variant_id === this.variantId);
            return !!size && size.stock + size.next_stock_quantity > 0;
        },

        request() {
            return { articles: [[this.variantId, 1]], printings: [], has_packaging: 0, sample: 1 };
        },

        async refresh() {
            if (!this.variantId) {
                this.summary = null;
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                const response = await fetch(config.endpoints.summary, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(this.request()),
                });
                if (!response.ok) throw new Error(response.statusText);
                this.summary = await response.json();
            } catch (e) {
                this.summary = null;
                this.error = config.labels.error;
            } finally {
                this.loading = false;
            }
        },

        shippingDate() {
            if (!this.summary?.shipping_date) return null;
            const [y, m, d] = this.summary.shipping_date.split('-');
            return `${d}/${m}/${y}`;
        },

        addToCart() {
            if (!this.variantId || !this.inStock()) return;
            const form = this.$refs.sampleForm;
            form.querySelector('input[name="add_to_cart"]').value = JSON.stringify(this.request());
            form.submit();
        },
    };
}
