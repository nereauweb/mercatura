<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** The JSON the configurator JS consumes (resources/js/storefront/product-configurator.js): shapes, not only 404s. */
final class ConfiguratorEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sizes_and_colours_cascade(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();

        $sizes = $this->postJson('/prodotti/personalizzazione/immagine_dimensioni', ['printing_id' => $f->screenA->id])->assertOk()->json();
        $this->assertNull($sizes['image']);
        $this->assertCount(1, $sizes['areas']);
        $this->assertSame('10x10', $sizes['areas'][0]['label']);
        $this->assertSame(100, (int) $sizes['areas'][0]['width_mm']);
        $sizeId = (int) $sizes['areas'][0]['id'];

        $colors = $this->postJson('/prodotti/personalizzazione/colori', ['printing_size_id' => $sizeId])->assertOk()->json();
        $this->assertSame(['1', '2'], array_column($colors, 'label'));
        $this->assertSame([$f->screenOneColorA->id, $f->screenTwoColorsA->id], array_map('intval', array_column($colors, 'id')));

        $this->postJson('/prodotti/personalizzazione/immagine_dimensioni', ['printing_id' => 999999999])->assertNotFound();
        $this->postJson('/prodotti/personalizzazione/colori', ['printing_size_id' => 999999999])->assertNotFound();
    }

    public function test_configurator_html_and_product_page_offer_the_print_options(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();

        $html = $this->post('/prodotti/configuratore', ['article_id' => $f->a->id])->assertOk()->getContent();
        $this->assertStringContainsString('Serigrafia', $html);
        $this->assertStringContainsString('Ricamo', $html);
        $this->assertStringContainsString(__('frontend.product.configurator.step_printing'), $html);

        $page = $this->get('/prodotti/'.$f->product->slug)->assertOk()->getContent();
        $this->assertStringContainsString('productConfigurator(', $page);
        $this->assertStringContainsString('Serigrafia', $page, 'recommended technique from the default print');
    }
}
