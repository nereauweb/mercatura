/**
 * Report dialog (docs/06_USER_REPORTS.md R4): sends a report of the current
 * page to the admin through the JSON endpoint. The page URL, the viewport and
 * the last JavaScript errors of the page travel with it; the server decides
 * who the sender is. Opened by a `report-open` window event.
 */
const MAX_ERRORS = 5;
const recentErrors = [];

function remember(text) {
    if (!text) return;
    recentErrors.push(String(text).slice(0, 300));
    if (recentErrors.length > MAX_ERRORS) recentErrors.shift();
}

window.addEventListener('error', (event) => {
    remember(event.message ? `${event.message} (${(event.filename || '').split('/').pop()}:${event.lineno || 0})` : null);
});
window.addEventListener('unhandledrejection', (event) => {
    const reason = event.reason;
    remember(reason && reason.message ? `Unhandled rejection: ${reason.message}` : 'Unhandled rejection');
});

export function reportDialog(config) {
    return {
        open: false,
        sending: false,
        sent: false,
        errors: [],
        fieldErrors: {},
        files: [],
        sentText: '',

        show() {
            this.sent = false;
            this.errors = [];
            this.fieldErrors = {};
            this.open = true;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.title && this.$refs.title.focus());
        },

        close() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
        },

        pick(event) {
            const chosen = Array.from(event.target.files || []);
            if (chosen.length > config.maxFiles) {
                this.errors = [config.labels.tooManyFiles];
                event.target.value = '';
                this.files = [];
                return;
            }
            this.errors = [];
            this.files = chosen.map((file) => ({ name: file.name, size: Math.ceil(file.size / 1024) }));
        },

        async send() {
            this.errors = [];
            this.fieldErrors = {};
            const form = this.$refs.form;
            const driver = window.mercaturaCaptcha;
            if (config.captchaField && driver && typeof driver.refresh === 'function' && document.getElementById(config.captchaField)) {
                try { await driver.refresh(config.captchaField, config.captchaAction); } catch (e) {}
            }
            const body = new FormData(form);
            body.set('page_url', window.location.href);
            body.set('viewport', `${window.innerWidth}x${window.innerHeight}`);
            recentErrors.forEach((error) => body.append('js_errors[]', error));

            this.sending = true;
            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                });
                const data = await response.json().catch(() => ({}));
                if (response.ok) {
                    this.sentText = data.message || '';
                    this.sent = true;
                    form.reset();
                    this.files = [];
                    return;
                }
                if (response.status === 422 && data.errors) {
                    this.fieldErrors = data.errors;
                    this.errors = Object.values(data.errors).flat();
                    return;
                }
                this.errors = [data.message || config.labels.error];
            } catch (e) {
                this.errors = [config.labels.error];
            } finally {
                this.sending = false;
            }
        },
    };
}
