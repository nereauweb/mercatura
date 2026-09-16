/**
 * Modal configurator (docs/04_STOREFRONT_FLOWS.md §4.2): five accordion
 * steps — colours, quantities per colour and size, the decoration question,
 * printing (several positions, each with its own technique), artwork,
 * summary with shipping date. Money and dates come from the core endpoints
 * (options tree, JSON summary); this component collects the choices and
 * renders the answers.
 */
export function productConfiguratorModal(config) {
    const money = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });

    return {
        endpoints: config.endpoints,
        csrf: config.csrf,
        hasPrinting: config.hasPrinting,
        hasPackaging: config.hasPackaging,
        artworkEnabled: config.artworkEnabled,
        minQuantity: config.minQuantity ?? 0,
        colors: config.colors ?? [],
        labels: config.labels ?? {},

        open: false,
        step: 1,
        done: { 1: false, 2: false, 3: false, 4: false },
        selectedColors: [],
        quantities: {},
        decoration: null, // 'yes' | 'no'
        packaged: false,
        tree: null,
        technique: null, // technique id currently browsed
        positionChoice: '', // "<technique id>:<option id>" in the position select
        selections: [], // { technique_id, option_id, technique, position, area, option, unit_price, setup, start_cost }
        artwork: {}, // option id => { token, name }
        summary: null,
        loading: false,
        error: null,
        quantityErrors: {},

        init() {
            if (window.location.hash === '#request-configurator') this.show();
        },

        show() {
            this.open = true;
            document.body.classList.add('overflow-hidden');
            if (this.colors.length === 1) {
                this.selectedColors = [this.colors[0].color_id];
                this.done[1] = true;
                this.step = 2;
            }
        },

        close() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
        },

        format(value) {
            return money.format(value ?? 0);
        },

        // Step 1 — colours
        toggleColor(colorId) {
            const index = this.selectedColors.indexOf(colorId);
            if (index === -1) this.selectedColors.push(colorId);
            else this.selectedColors.splice(index, 1);
        },

        isSelected(colorId) {
            return this.selectedColors.includes(colorId);
        },

        selectedColorRows() {
            return this.colors.filter((c) => this.selectedColors.includes(c.color_id));
        },

        goToQuantities() {
            if (this.selectedColors.length === 0) return;
            this.done[1] = true;
            this.step = 2;
        },

        // Step 2 — quantities
        quantityOf(variantId) {
            return parseInt(this.quantities[variantId] ?? 0, 10) || 0;
        },

        validateQuantity(size) {
            const value = this.quantityOf(size.variant_id);
            let message = null;
            if (value > 0 && value < this.minQuantity) message = this.labels.belowMinimum.replace(':min', this.minQuantity);
            else if (value > size.stock + size.next_stock_quantity) message = this.labels.overMaximum;
            else if (value > size.stock && size.next_stock_quantity > 0) message = this.labels.restockNotice;
            this.quantityErrors[size.variant_id] = message;
            return message;
        },

        quantityState(size) {
            const message = this.quantityErrors[size.variant_id];
            if (!message) return 'ok';
            return message === this.labels.restockNotice ? 'notice' : 'error';
        },

        articles() {
            return Object.entries(this.quantities)
                .map(([id, qty]) => [parseInt(id, 10), parseInt(qty, 10) || 0])
                .filter(([, qty]) => qty > 0);
        },

        totalQuantity() {
            return this.articles().reduce((sum, [, qty]) => sum + qty, 0);
        },

        // Variants whose quantity is below the minimum: the sample request is offered for the first one (§4.6).
        belowMinimumVariant() {
            if (!config.samples) return null;
            const found = Object.entries(this.quantityErrors).find(([, message]) => message && message !== this.labels.restockNotice && message !== this.labels.overMaximum);
            return found ? parseInt(found[0], 10) : null;
        },

        requestSample() {
            const variantId = this.belowMinimumVariant();
            if (!variantId) return;
            this.close();
            window.dispatchEvent(new CustomEvent('sample-request-open', { detail: { variantId } }));
        },

        quantitiesValid() {
            if (this.totalQuantity() === 0) return false;
            return this.selectedColorRows().every((color) => color.sizes.every((size) => this.quantityState(size) !== 'error'));
        },

        async chooseDecoration(choice) {
            if (!this.quantitiesValid()) {
                this.error = this.labels.completeQuantities;
                return;
            }
            this.error = null;
            this.done[2] = true;
            if (choice === 'quote') {
                if (config.quickQuoteModal) {
                    // Hand the current article and the total quantity to the quick-quote modal (§4.4).
                    this.close();
                    window.dispatchEvent(new CustomEvent('quick-quote-open', { detail: { articleId: config.articleId, quantity: this.totalQuantity() } }));
                    return;
                }
                window.location.href = this.endpoints.quote + '#quantities=' + encodeURIComponent(JSON.stringify(this.articles()));
                return;
            }
            this.decoration = choice;
            if (choice === 'no') {
                this.selections = [];
                this.packaged = false;
                this.done[3] = true;
                this.done[4] = true;
                await this.refreshSummary();
                this.step = 5;
                return;
            }
            await this.loadTree();
            this.step = 3;
        },

        // Step 3 — printing
        async loadTree() {
            this.loading = true;
            try {
                this.tree = await this.post(this.endpoints.options, { article_id: config.articleId, articles: this.articles() });
                this.technique = this.techniques()[0]?.id ?? null;
            } catch (e) {
                this.error = this.labels.summaryError;
            } finally {
                this.loading = false;
            }
        },

        // Every technique offered on any position, once (the buttons row).
        techniques() {
            const seen = new Map();
            (this.tree?.positions ?? []).forEach((position) => {
                position.techniques.forEach((technique) => {
                    if (!seen.has(technique.label)) seen.set(technique.label, { id: technique.id, label: technique.label });
                });
            });
            return [...seen.values()];
        },

        techniqueLabel(id) {
            return this.techniques().find((t) => t.id === id)?.label ?? '';
        },

        // Position/area/option combinations priced for the browsed technique.
        positionOptions() {
            const rows = [];
            const label = this.techniqueLabel(this.technique);
            (this.tree?.positions ?? []).forEach((position) => {
                position.techniques.filter((t) => t.label === label).forEach((technique) => {
                    technique.areas.forEach((area) => {
                        area.options.forEach((option) => {
                            rows.push({
                                key: technique.id + ':' + option.id,
                                technique_id: technique.id,
                                option_id: option.id,
                                technique: technique.label,
                                position: position.label,
                                area: area.label,
                                option: option.label,
                                unit_price: option.unit_price,
                                setup: option.setup,
                                start_cost: option.start_cost,
                                image: technique.image ?? position.image,
                                text: `${position.label} — ${area.label} — ${option.label} — ${this.format(option.unit_price)}/pz` + (option.setup > 0 ? `, ${this.labels.setup} ${this.format(option.setup)}` : ''),
                            });
                        });
                    });
                });
            });
            return rows;
        },

        chosenPosition() {
            return this.positionOptions().find((r) => r.key === this.positionChoice) ?? null;
        },

        previewImage() {
            return this.chosenPosition()?.image ?? this.tree?.positions?.[0]?.image ?? null;
        },

        async addSelection() {
            const row = this.chosenPosition();
            if (!row) return;
            // One decoration per position: choosing again replaces it.
            this.selections = this.selections.filter((s) => s.position !== row.position);
            this.selections.push({ ...row });
            this.positionChoice = '';
            await this.refreshSummary();
        },

        async removeSelection(index) {
            const [removed] = this.selections.splice(index, 1);
            if (removed) delete this.artwork[removed.option_id];
            await this.refreshSummary();
        },

        async togglePackaging(value) {
            this.packaged = value;
            await this.refreshSummary();
        },

        async goToArtwork() {
            if (this.selections.length === 0) return;
            this.done[3] = true;
            if (!this.artworkEnabled) {
                this.done[4] = true;
                this.step = 5;
                return;
            }
            this.step = 4;
        },

        // Step 4 — artwork
        async uploadArtwork(event, optionId) {
            const file = event.target.files?.[0];
            if (!file) return;
            const body = new FormData();
            body.append('file', file);
            body.append('option_id', optionId);
            this.loading = true;
            try {
                const response = await fetch(this.endpoints.artwork, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' }, body });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message ?? response.statusText);
                this.artwork[optionId] = { token: data.token, name: data.name };
                this.error = null;
            } catch (e) {
                this.error = e.message || this.labels.artworkError;
            } finally {
                this.loading = false;
            }
        },

        goToSummary() {
            this.done[4] = true;
            this.step = 5;
        },

        // Step 5 — summary
        request() {
            return {
                articles: this.articles(),
                printings: this.decoration === 'yes' ? this.selections.map((s) => s.option_id) : [],
                has_packaging: this.packaged ? 1 : 0,
                artwork: Object.fromEntries(Object.entries(this.artwork).map(([id, file]) => [id, file.token])),
            };
        },

        async refreshSummary() {
            if (this.totalQuantity() === 0) {
                this.summary = null;
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                this.summary = await this.post(this.endpoints.summary, this.request());
            } catch (e) {
                this.summary = null;
                this.error = this.labels.summaryError;
            } finally {
                this.loading = false;
            }
        },

        // Rows of the pre-cart table, built from the JSON summary.
        summaryRows() {
            if (!this.summary) return [];
            const rows = [];
            this.summary.articles.forEach((article) => {
                rows.push({ kind: 'article', sku: article.sku, color: article.color, color_code: article.color_code, size: article.size, quantity: article.quantity, unit_price: article.unit_price, price: article.price });
                article.customizations.forEach((c) => {
                    rows.push({ kind: 'customization', label: c.label, quantity: c.quantity, unit_price: c.unit_price, price: c.price });
                    if (c.packaging_price > 0) rows.push({ kind: 'packaging', label: this.labels.packaging, quantity: c.quantity, unit_price: c.packaging_unit_price, price: c.packaging_price });
                });
            });
            this.summary.customizations.forEach((c) => {
                if (c.start_cost > 0) rows.push({ kind: 'extra', label: `${this.labels.startCost} — ${c.technique}`, price: c.start_cost });
                rows.push({ kind: 'extra', label: `${this.labels.setupCost} — ${c.technique} ${c.position}`, price: c.setup_price, free: c.setup_price <= 0 });
            });
            if (this.summary.surcharge > 0) rows.push({ kind: 'extra', label: this.labels.surcharge.replace(':min', this.summary.minimum_quantity), price: this.summary.surcharge });
            return rows;
        },

        shippingDate() {
            if (!this.summary?.shipping_date) return null;
            const [y, m, d] = this.summary.shipping_date.split('-');
            return `${d}/${m}/${y}`;
        },

        addToCart() {
            if (this.articles().length === 0) {
                window.alert(this.labels.selectVariantFirst);
                return;
            }
            const form = this.$refs.cartForm;
            form.querySelector('input[name="add_to_cart"]').value = JSON.stringify(this.request());
            if (config.conversion && typeof window.canTrackAnalytics === 'function' && window.canTrackAnalytics()) {
                let submitted = false;
                const submit = () => { if (!submitted) { submitted = true; form.submit(); } };
                window.gtag('event', 'conversion', { send_to: config.conversion, event_callback: submit });
                setTimeout(submit, 1500);
                return;
            }
            form.submit();
        },

        async post(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            if (!response.ok) throw new Error(response.statusText);
            return response.json();
        },
    };
}
