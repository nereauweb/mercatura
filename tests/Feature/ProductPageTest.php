<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/** Phase 4(d): product page and print configurator. */
final class ProductPageTest extends TestCase
{
    /** @return list<string> */
    private function areaFiles(): array
    {
        $files = [
            resource_path('views/frontend/pages/product.blade.php'),
            resource_path('views/frontend/elements/product-bestsellers.blade.php'),
            resource_path('views/frontend/components/usp.blade.php'),
            resource_path('js/storefront/product-configurator.js'),
        ];
        foreach (Finder::create()->files()->in(resource_path('views/frontend/components/product'))->name('*.blade.php') as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    public function test_product_templates_contain_no_uikit_jquery_or_client_identity(): void
    {
        foreach ($this->areaFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, basename($file).' still carries UIkit classes');
            $this->assertDoesNotMatchRegularExpression('/\$\(|jQuery|UIkit\./', $source, basename($file).' still uses jQuery or UIkit');
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern('fg-|bg-blu|bg-arancione|bg-grigio'), $source, basename($file).' still carries client content');
        }
    }

    public function test_legacy_card_partials_are_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/frontend/elements/product-card.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/frontend/elements/bestseller-card.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/frontend/elements/product-configurator.blade.php'));
        $this->assertSame(0, count(iterator_to_array(Finder::create()->files()->in(resource_path('views'))->exclude('admin')->contains('frontend.elements.product-card'))));
    }

    public function test_unknown_product_returns_404(): void
    {
        $this->get('/prodotti/questo-prodotto-non-esiste')->assertNotFound();
        $this->get('/prodotti/id/999999999')->assertNotFound();
    }

    public function test_configurator_endpoints_reject_unknown_ids_and_accept_json(): void
    {
        $this->postJson('/prodotti/personalizzazione/immagine_dimensioni', ['printing_id' => 999999999])->assertNotFound();
        $this->postJson('/prodotti/personalizzazione/colori', ['printing_size_id' => 999999999])->assertNotFound();
    }

    public function test_catalog_attribute_ids_and_conversion_labels_are_configurable(): void
    {
        $this->assertIsInt(config('mercatura.catalog.attributes.material'));
        $this->assertIsArray(config('mercatura.catalog.per_size_price_table_categories'));
        $this->assertNull(config('gtm.conversions.add_to_cart'));
    }
}
