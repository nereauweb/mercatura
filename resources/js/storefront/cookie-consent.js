/**
 * Cookie consent banner: sets the consent cookies, loads Google Tag Manager
 * (or updates Consent Mode v2) after acceptance, shows a short confirmation.
 * The banner overlays the page: it never pushes layout.
 */
export function cookieConsent(config) {
    return {
        visible: true,
        toast: '',

        setCookie(name, value) {
            const date = new Date();
            date.setTime(date.getTime() + config.lifetimeDays * 24 * 60 * 60 * 1000);
            document.cookie = `${name}=${encodeURIComponent(value)}; expires=${date.toUTCString()}; path=/`
                + (config.domain ? `; domain=${config.domain}` : '') + (config.secure ? '; secure' : '') + `; samesite=${config.sameSite}`;
        },

        loadTagManager() {
            const id = config.gtmId;
            if (!id || document.querySelector(`script[src*="googletagmanager.com/gtm.js?id=${id}"]`)) return;
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            const script = document.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtm.js?id=${id}`;
            document.head.appendChild(script);
        },

        accept() {
            this.setCookie(config.cookieName, '1');
            this.setCookie('cookies_analytics', '1');
            this.visible = false;
            this.notify(config.labels.saved);
            if (config.consentModeV2) {
                if (typeof window.gtag === 'function') {
                    window.gtag('consent', 'update', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted' });
                }
            } else {
                this.loadTagManager();
            }
        },

        reject() {
            this.setCookie(config.cookieName, '1');
            this.setCookie('cookies_analytics', '0');
            this.visible = false;
            this.notify(config.labels.essentialOnly);
        },

        notify(message) {
            this.toast = message;
            setTimeout(() => { this.toast = ''; }, 4000);
        },
    };
}
