<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/04_STOREFRONT_FLOWS.md §4.2: the product page mounts the modal flow when configured, the panel otherwise. */
final class ConfiguratorModalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_page_mounts_the_flow_the_installation_chose(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $url = '/prodotti/'.$f->product->slug;

        $panel = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('productConfigurator(JSON.parse(', $panel);
        $this->assertStringNotContainsString('&amp;quot;', $panel);
        $this->assertStringContainsString(__('frontend.product.configurator.title'), $panel);
        $this->assertStringNotContainsString('productConfiguratorModal(', $panel);

        config(['mercatura.storefront.configurator' => 'modal', 'mercatura.storefront.artwork_in_configurator' => true]);
        $modal = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('productConfiguratorModal(JSON.parse(', $modal, 'the config is embedded with @js: attribute-safe, no double escaping');
        $this->assertStringNotContainsString('&amp;quot;', $modal);
        $this->assertStringContainsString(__('frontend.product.buy_modal'), $modal);
        $this->assertStringContainsString(__('frontend.product.configurator_modal.decoration_question'), $modal);
        $this->assertStringContainsString(__('frontend.product.configurator_modal.step_artwork'), $modal);
        $this->assertStringContainsString('role="dialog"', $modal);
        $this->assertStringContainsString($f->a->sku, $modal, 'the quantity table shows the article codes');
        $this->assertStringNotContainsString(__('frontend.product.configurator.title'), $modal);
    }
}
