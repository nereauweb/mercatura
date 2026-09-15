/**
 * Header search box: debounced suggestions from the core suggest endpoint.
 * Local state only; the server decides the search engine (SearchEngine
 * contract, Phase 5). Results render from the JSON, no provider SDK here.
 */
export function headerSearch(config) {
    return {
        terms: '',
        results: [],
        count: null,
        open: false,
        loading: false,
        error: false,
        timer: null,
        lastTerms: '',
        endpoint: config.endpoint,
        minLength: config.minLength ?? 2,

        onInput() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(), config.debounce ?? 300);
        },

        submit() {
            clearTimeout(this.timer);
            this.search();
        },

        async search() {
            const terms = this.terms.trim();
            if (terms.length < this.minLength) {
                this.close();
                return;
            }
            if (terms === this.lastTerms && this.open) {
                return;
            }
            this.lastTerms = terms;
            this.loading = true;
            this.error = false;
            this.open = true;
            try {
                const response = await fetch(this.endpoint.replace('__TERMS__', encodeURIComponent(terms)), {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error(response.statusText);
                this.results = await response.json();
                this.count = this.results.length;
            } catch (e) {
                this.error = true;
                this.results = [];
                this.count = null;
            } finally {
                this.loading = false;
            }
        },

        close() {
            this.open = false;
        },
    };
}
