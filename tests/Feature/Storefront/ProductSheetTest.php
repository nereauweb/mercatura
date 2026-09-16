<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/04_STOREFRONT_FLOWS.md §4.3: the product sheet tabs fetched on first open. */
final class ProductSheetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_three_sheets_render_as_fragments(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $base = '/prodotti/'.$f->product->slug.'/'.$f->a->sku.'/scheda/';

        $details = $this->get($base.'dettagli')->assertOk()->getContent();
        $this->assertStringContainsString(__('frontend.product.default_customization.title'), $details);
        $this->assertStringContainsString('Serigrafia', $details);
        $this->assertStringContainsString('10x10', $details);

        $stock = $this->get($base.'disponibilita')->assertOk()->getContent();
        $this->assertStringContainsString($f->b->sku, $stock);
        $this->assertStringContainsString('Rosso', $stock);
        $this->assertStringContainsString('1000', $stock);

        $prices = $this->get($base.'listino')->assertOk()->getContent();
        $this->assertStringContainsString(__('frontend.product.price_neutral'), $prices);

        $this->get($base.'altro')->assertNotFound();
        $this->get('/prodotti/'.$f->product->slug.'/NOPE/scheda/dettagli')->assertNotFound();

        $html = view('frontend.components.product.tabs', ['tabs' => [['key' => 'details', 'label' => 'Dettagli', 'slug' => null], ['key' => 'stock', 'label' => 'Disponibilità', 'slug' => 'disponibilita']], 'endpoint' => rtrim($base, '/'), 'slot' => new \Illuminate\Support\HtmlString('<p>inline</p>'), 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('inline', $html);
        $this->assertStringContainsString('disponibilita', $html);
    }
}
