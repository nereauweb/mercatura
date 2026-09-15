/**
 * Product configurator: quantities per colour and size, printed or plain
 * goods, packaging, one print selection per position, live summary from the
 * core pricing endpoint. All prices are computed server-side; this component
 * only collects the request and renders the response.
 */
export function productConfigurator(config) {
    return {
        endpoints: config.endpoints,
        csrf: config.csrf,
        hasPrinting: config.hasPrinting,
        hasPackaging: config.hasPackaging,
        minQuantity: config.minQuantity ?? 0,
        positions: config.positions ?? [],

        open: false,
        step: 1,
        selectedColors: [],
        quantities: {},
        printed: false,
        packaged: false,
        selections: {},
        summary: null,
        summaryError: false,
        loading: false,
        confirmed: false,

        init() {
            this.positions.forEach((position) => {
                this.selections[position.id] = { technique: '', size: '', color: '', sizes: [], colors: [], image: position.image, added: false, visible: false };
            });
            if (window.location.hash === '#request-configurator') {
                this.open = true;
            }
        },

        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.root?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },

        toggleColor(colorId) {
            const index = this.selectedColors.indexOf(colorId);
            if (index === -1) {
                this.selectedColors.push(colorId);
            } else {
                this.selectedColors.splice(index, 1);
            }
        },

        isSelected(colorId) {
            return this.selectedColors.includes(colorId);
        },

        quantityState(variant) {
            const value = parseInt(this.quantities[variant.variant_id] ?? 0, 10) || 0;
            if (value > variant.stock) return 'over';
            if (value !== 0 && value < this.minQuantity) return 'under';
            return 'ok';
        },

        clampQuantity(variant) {
            const value = parseInt(this.quantities[variant.variant_id] ?? 0, 10) || 0;
            if (value > variant.stock) {
                this.quantities[variant.variant_id] = variant.stock;
            }
        },

        articles() {
            return Object.entries(this.quantities)
                .map(([id, qty]) => [parseInt(id, 10), parseInt(qty, 10) || 0])
                .filter(([, qty]) => qty > 0);
        },

        totalQuantity() {
            return this.articles().reduce((sum, [, qty]) => sum + qty, 0);
        },

        canConfirm() {
            return this.totalQuantity() > 0 && this.articles().every(([, qty]) => qty >= this.minQuantity);
        },

        confirmQuantities() {
            if (!this.canConfirm()) return;
            this.confirmed = true;
            this.updateSummary();
            if (this.hasPrinting) this.step = 2;
        },

        choosePrinted(value) {
            this.printed = value;
            if (value) {
                this.step = this.hasPackaging ? 3 : 4;
            } else {
                Object.values(this.selections).forEach((s) => Object.assign(s, { technique: '', size: '', color: '', sizes: [], colors: [], added: false }));
                this.updateSummary();
            }
        },

        choosePackaged(value) {
            this.packaged = value;
            this.step = 4;
            this.updateSummary();
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

        async techniqueChanged(positionId) {
            const selection = this.selections[positionId];
            selection.size = '';
            selection.color = '';
            selection.colors = [];
            selection.sizes = [];
            if (!selection.technique) return;
            try {
                const data = await this.post(this.endpoints.sizes, { printing_id: selection.technique });
                selection.sizes = data.areas ?? [];
                if (data.image) selection.image = data.image;
            } catch (e) {
                selection.sizes = [];
            }
        },

        async sizeChanged(positionId) {
            const selection = this.selections[positionId];
            selection.color = '';
            selection.colors = [];
            if (!selection.size) return;
            try {
                const colors = await this.post(this.endpoints.colors, { printing_size_id: selection.size });
                selection.colors = colors.map((c) => ({ id: c.id, label: c.label === '0' || c.label === 0 ? config.labels.fourColour : c.label }));
            } catch (e) {
                selection.colors = [];
            }
        },

        togglePrinting(positionId) {
            const selection = this.selections[positionId];
            if (selection.added) {
                Object.assign(selection, { technique: '', size: '', color: '', sizes: [], colors: [], added: false });
            } else if (selection.color) {
                selection.added = true;
            }
            this.updateSummary();
        },

        printings() {
            return Object.values(this.selections).filter((s) => s.added && s.color).map((s) => parseInt(s.color, 10));
        },

        request() {
            return { articles: this.articles(), printings: this.printed ? this.printings() : [], has_packaging: this.packaged ? 1 : 0 };
        },

        async updateSummary() {
            if (this.totalQuantity() === 0) {
                this.summary = null;
                return;
            }
            this.loading = true;
            this.summaryError = false;
            try {
                this.summary = await this.post(this.endpoints.summary, this.request());
            } catch (e) {
                this.summaryError = true;
            } finally {
                this.loading = false;
            }
        },

        printSummary() {
            const form = this.$refs.printForm;
            form.querySelector('input[name="summary_data"]').value = JSON.stringify(this.request());
            form.submit();
        },

        addToCart() {
            if (this.articles().length === 0) {
                window.alert(config.labels.selectVariantFirst);
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
    };
}
