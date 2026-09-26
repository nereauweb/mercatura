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
        $this->assertStringContainsString(__('frontend.product.price_printed'), $prices);
        $this->assertStringContainsString(__('frontend.product.printed'), $prices);

        // Customization options: one card per technique and position, the print area, the colours, the processing days;
        // the fixture has no supplier image, so every card carries the neutral placeholder.
        $options = $this->get($base.'personalizzazioni')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('frontend.product.customization_options.note')), $options);
        $this->assertStringContainsString('Serigrafia', $options);
        $this->assertStringContainsString('100 mm', $options, 'width of the 10x10 area');
        $this->assertStringContainsString(__('frontend.product.customization_options.no_image'), $options);
        $this->assertStringNotContainsString('<img', $options, 'no image, only the placeholder');
        \App\Models\Customizations\Customization::query()->whereKey($f->screenA->id)->update(['image' => 'https://example.com/pos.jpg', 'max_colors' => '4']);
        \Illuminate\Support\Facades\Cache::flush();
        $options = $this->get($base.'personalizzazioni')->assertOk()->getContent();
        $this->assertStringContainsString('src="https://example.com/pos.jpg"', $options);
        $this->assertStringContainsString('x-on:error="broken = true"', $options, 'a broken supplier image falls back to the placeholder');
        $this->assertStringContainsString(__('frontend.product.customization_options.max_colors', ['count' => 4]), $options);

        $this->get($base.'altro')->assertNotFound();
        $this->get('/prodotti/'.$f->product->slug.'/NOPE/scheda/dettagli')->assertNotFound();

        $html = view('frontend.components.product.tabs', ['tabs' => [['key' => 'details', 'label' => 'Dettagli', 'slug' => null], ['key' => 'stock', 'label' => 'Disponibilità', 'slug' => 'disponibilita']], 'endpoint' => rtrim($base, '/'), 'slot' => new \Illuminate\Support\HtmlString('<p>inline</p>'), 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('inline', $html);
        $this->assertStringContainsString('disponibilita', $html);
    }
}
