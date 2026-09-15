/**
 * Quotation request form: client-side checks before the "add product" and
 * "send" submits, stock lookup when colour or size change, product removal.
 */
export function quotationForm(config) {
    return {
        intent: 'add',
        errors: [],
        stock: config.stock ?? null,
        colorId: config.colorId ?? null,
        sizeId: config.sizeId ?? null,
        quantity: config.quantity ?? 0,
        image: config.image ?? '',

        get overQuantity() {
            return this.stock !== null && parseInt(this.quantity, 10) > this.stock;
        },

        async colorChanged(event) {
            const option = event.target.selectedOptions[0];
            this.colorId = option.dataset.colorId;
            try {
                const response = await fetch(`${config.endpoints.cover}/${option.dataset.colorArticleId}/cover`);
                if (response.ok) this.image = await response.text();
            } catch (e) {}
            await this.updateStock();
        },

        async sizeChanged(event) {
            this.sizeId = event.target.selectedOptions[0].dataset.sizeId;
            await this.updateStock();
        },

        async updateStock() {
            try {
                const response = await fetch(config.endpoints.stock, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                    body: JSON.stringify({ product_id: config.productId, color_id: this.colorId, size_id: this.sizeId }),
                });
                if (response.ok) this.stock = parseInt(await response.text(), 10);
            } catch (e) {}
        },

        submit(intent) {
            this.intent = intent;
            this.errors = [];
            const form = this.$refs.form;
            form.querySelectorAll('[data-quantity]').forEach((input) => {
                const value = parseInt(String(input.value).trim(), 10);
                if (Number.isNaN(value) || value <= 0) this.errors.push(config.messages.quantity);
                else input.value = value;
            });
            const isEdit = !!form.querySelector('input[name="_method"][value="put"]');
            if (!isEdit && intent === 'send') {
                const email = form.querySelector('[name="customer[email]"]');
                if (!email || !/^[\w.+-]+@([\w-]+\.)+[\w-]{2,}$/.test(email.value)) this.errors.push(config.messages.email);
                const consent = form.querySelector('[name="consent_gdpr"]');
                if (!consent || !consent.checked) this.errors.push(config.messages.privacy);
            }
            if (this.errors.length) {
                this.$nextTick(() => this.$refs.errors?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }
            form.setAttribute('action', !isEdit && intent === 'send' ? config.endpoints.send : config.endpoints.default);
            form.requestSubmit();
        },

        remove(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${config.endpoints.remove}/${id}/elimina`;
            form.innerHTML = `<input type="hidden" name="_token" value="${config.csrf}"><input type="hidden" name="_method" value="delete">`;
            document.body.appendChild(form);
            form.submit();
        },
    };
}
