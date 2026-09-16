import './bootstrap';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { headerSearch } from './storefront/header-search';
import { categoriesMenu } from './storefront/categories-menu';
import { flashMessages } from './storefront/flash-messages';
import { slideshow } from './storefront/slideshow';
import { productConfigurator } from './storefront/product-configurator';
import { productConfiguratorModal } from './storefront/product-configurator-modal';
import { customerForm } from './storefront/customer-form';
import { quotationForm } from './storefront/quotation-form';
import { initFormErrors } from './storefront/form-errors';
import { initCaptchaRefresh } from './storefront/captcha-refresh';
import { cookieConsent } from './storefront/cookie-consent';

// Livewire ships its own Alpine; one bundle, one Alpine instance.
Alpine.data('headerSearch', headerSearch);
Alpine.data('categoriesMenu', categoriesMenu);
Alpine.data('flashMessages', flashMessages);
Alpine.data('slideshow', slideshow);
Alpine.data('productConfigurator', productConfigurator);
Alpine.data('productConfiguratorModal', productConfiguratorModal);
Alpine.data('customerForm', customerForm);
Alpine.data('quotationForm', quotationForm);
Alpine.data('cookieConsent', cookieConsent);

document.addEventListener('DOMContentLoaded', () => {
    initFormErrors();
    initCaptchaRefresh();
});

Livewire.start();
