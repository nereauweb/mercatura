/**
 * Quick-quote modal (docs/04_STOREFRONT_FLOWS.md §4.4): contact fields on the
 * left, the products to quote on the right, everything through the JSON
 * endpoints that share the session store with the quotation page. Opened by
 * a `quick-quote-open` window event ({ articleId?, quantity? }); the
 * header counter ([data-quote-count]) follows every change.
 */
export function quickQuote(config) {
    const jsonHeaders = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' });

    return {
        open: false,
        loading: false,
        loaded: false,
        sent: false,
        errors: [],
        fieldErrors: {},
        customer: Object.assign({ name: '', surname: '', email: '', company: '', customer_type: '', activity: '', phone: '' }, config.customer ?? {}),
        products: [],
        count: config.count ?? 0,

        async openWith(detail) {
            this.sent = false;
            this.errors = [];
            this.fieldErrors = {};
            this.open = true;
            document.body.classList.add('overflow-hidden');
            if (detail && detail.articleId) await this.add(detail.articleId, detail.quantity ?? 0);
            else if (!this.loaded) await this.load();
        },

        close() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
        },

        apply(data) {
            this.products = data.products ?? [];
            this.count = data.count ?? this.products.length;
            if (data.customer && Object.keys(data.customer).length) this.customer = Object.assign({}, this.customer, data.customer);
            this.loaded = true;
            document.querySelectorAll('[data-quote-count]').forEach((el) => { el.textContent = this.count; });
        },

        load() {
            return this.call('GET', config.endpoints.list);
        },

        add(articleId, quantity) {
            return this.call('POST', config.endpoints.add, { article_id: articleId, quantity: parseInt(quantity, 10) || 0 });
        },

        update(product) {
            return this.call('PUT', config.endpoints.update.replace('__ID__', product.id), {
                quantity: parseInt(product.quantity, 10) || 0,
                notes: product.notes ?? '',
                color: product.color ?? '',
                size: product.size ?? '',
                printing: product.printing ?? '',
            });
        },

        remove(id) {
            return this.call('DELETE', config.endpoints.remove.replace('__ID__', id));
        },

        async send() {
            this.errors = [];
            this.fieldErrors = {};
            const form = this.$refs.form;
            const driver = window.mercaturaCaptcha;
            if (driver && typeof driver.refresh === 'function' && document.getElementById(config.captchaField)) {
                try { await driver.refresh(config.captchaField, config.captchaAction); } catch (e) {}
            }
            this.loading = true;
            try {
                const response = await fetch(config.endpoints.send, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });
                const data = await response.json().catch(() => ({}));
                if (response.status === 422) {
                    this.fieldErrors = data.errors ?? {};
                    this.errors = Object.values(this.fieldErrors).flat();
                    return;
                }
                if (!response.ok) throw new Error(data.message ?? response.statusText);
                this.sent = true;
                this.apply({ products: [], count: 0 });
                if (config.conversion && typeof window.canTrackAnalytics === 'function' && window.canTrackAnalytics()) {
                    window.gtag('event', 'conversion', { send_to: config.conversion, currency: 'EUR' });
                }
            } catch (e) {
                this.errors = [e.message || config.labels.error];
            } finally {
                this.loading = false;
            }
        },

        async call(method, url, payload) {
            this.loading = true;
            try {
                const response = await fetch(url, { method, headers: jsonHeaders(), body: payload ? JSON.stringify(payload) : undefined });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message ?? response.statusText);
                this.errors = [];
                this.apply(data);
            } catch (e) {
                this.errors = [e.message || config.labels.error];
            } finally {
                this.loading = false;
            }
        },
    };
}
