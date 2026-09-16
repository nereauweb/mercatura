import './bootstrap';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { headerSearch } from './storefront/header-search';
import { categoriesMenu } from './storefront/categories-menu';
import { flashMessages } from './storefront/flash-messages';
import { slideshow } from './storefront/slideshow';
import { customerForm } from './storefront/customer-form';
import { quotationForm } from './storefront/quotation-form';
import { quickQuote } from './storefront/quick-quote';
import { initFormErrors } from './storefront/form-errors';
import { initCaptchaRefresh } from './storefront/captcha-refresh';
import { cookieConsent } from './storefront/cookie-consent';

// Livewire ships its own Alpine; one bundle, one Alpine instance.
Alpine.data('headerSearch', headerSearch);
Alpine.data('categoriesMenu', categoriesMenu);
Alpine.data('flashMessages', flashMessages);
Alpine.data('slideshow', slideshow);
Alpine.data('customerForm', customerForm);
Alpine.data('quotationForm', quotationForm);
Alpine.data('quickQuote', quickQuote);
Alpine.data('cookieConsent', cookieConsent);

document.addEventListener('DOMContentLoaded', () => {
    initFormErrors();
    initCaptchaRefresh();
});

// The product page's components (configurator, modal configurator, sample request) are a
// separate chunk: Alpine starts once they are registered, everywhere else immediately.
const productPage = document.getElementById('product') ? import('./storefront/product-page').then((m) => m.register(Alpine)) : Promise.resolve();
productPage.then(() => Livewire.start());
