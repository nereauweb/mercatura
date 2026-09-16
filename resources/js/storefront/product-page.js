/**
 * Product-page-only Alpine components, loaded as a separate chunk when the
 * page has #product (see app.js) so the main bundle stays under the
 * PERF_BASELINE.md budget on every other page.
 */
import { productConfigurator } from './product-configurator';
import { productConfiguratorModal } from './product-configurator-modal';
import { sampleRequest } from './sample-request';

export function register(Alpine) {
    Alpine.data('productConfigurator', productConfigurator);
    Alpine.data('productConfiguratorModal', productConfiguratorModal);
    Alpine.data('sampleRequest', sampleRequest);
}
