<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/04_STOREFRONT_FLOWS.md §4.1: the options tree and the JSON summary the modal configurator reads. */
final class ConfiguratorJsonTest extends TestCase
{
    use DatabaseTransactions;

    private CustomizationFixture $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->f = CustomizationFixture::create();
    }

    public function test_the_options_tree_is_priced_for_the_line_quantity(): void
    {
        $tree = $this->postJson('/prodotti/configuratore/opzioni', ['article_id' => $this->f->a->id, 'articles' => [[$this->f->a->id, 60], [$this->f->b->id, 40]]])->assertOk()->json();

        $this->assertSame(100, $tree['quantity']);
        $this->assertEqualsWithDelta(30.0, $tree['markup_percent'], 0.001);
        $this->assertTrue($tree['packaging']['available']);
        $this->assertSame(['Fronte', 'Retro'], array_column($tree['positions'], 'label'));
        $screen = $tree['positions'][0]['techniques'][0];
        $this->assertSame(['Serigrafia', 50, 5, true], [$screen['label'], $screen['minimum_quantity'], $screen['processing_days'], $screen['has_packaging']]);
        $option = $screen['areas'][0]['options'][0];
        $this->assertSame($this->f->screenOneColorA->id, $option['id']);
        $this->assertSame('1 colore', $option['label']);
        $this->assertEqualsWithDelta(0.78, $option['unit_price'], 0.001, 'tier 100 cost 0.60 with the article markup 30 %');
        $this->assertEqualsWithDelta(0.65, $option['packaging_unit_price'], 0.001);
        $this->assertEqualsWithDelta(30.0, $option['setup'], 0.001);
        $this->assertEqualsWithDelta(5.0, $option['start_cost'], 0.001);
        $this->assertEqualsWithDelta(60.0, $tree['positions'][1]['techniques'][0]['areas'][0]['options'][0]['setup'], 0.001, 'setup multiplier 0 counts as 1');
        $this->assertEqualsWithDelta(60.0, $screen['areas'][0]['options'][1]['setup'], 0.001, '2 colours: 30 × 2');

        $this->postJson('/prodotti/configuratore/opzioni', ['article_id' => 999999999])->assertNotFound();
    }

    public function test_the_summary_is_the_priced_line_with_shipping_and_vat(): void
    {
        config(['mercatura.storefront.shipping_date' => true, 'mercatura.delivery.holidays' => []]);
        $summary = $this->postJson('/prodotti/configuratore/riepilogo', ['articles' => [[$this->f->a->id, 60], [$this->f->b->id, 40]], 'customizations' => [$this->f->screenOneColorA->id], 'has_packaging' => 1])->assertOk()->json();

        $this->assertSame(100, $summary['quantity']);
        $this->assertEqualsWithDelta(1218.0, $summary['price'], 0.001);
        $this->assertEqualsWithDelta(0.0, $summary['shipping'], 0.001, 'free above 500');
        $this->assertEqualsWithDelta(267.96, $summary['vat'], 0.001);
        $this->assertEqualsWithDelta(1495.96, $summary['total'], 0.001, 'taxable + VAT + additional costs');
        $this->assertEqualsWithDelta(12.18, $summary['unit_price'], 0.001);
        $this->assertCount(2, $summary['articles']);
        $this->assertSame([$this->f->b->sku, 'Rosso', 40], [$summary['articles'][1]['sku'], $summary['articles'][1]['color'], $summary['articles'][1]['quantity']]);
        $this->assertEqualsWithDelta(31.2, $summary['articles'][1]['customizations'][0]['price'], 0.001);
        $this->assertSame('FRONTE - Serigrafia  10x10 1 colore', $summary['customizations'][0]['label']);
        $this->assertEqualsWithDelta(30.0, $summary['customizations'][0]['setup_price'], 0.001);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $summary['shipping_date']);

        // Under the minimum: the surcharge and shipping for a small order.
        $small = $this->postJson('/prodotti/configuratore/riepilogo', ['articles' => [[$this->f->a->id, 20]], 'printings' => [$this->f->screenOneColorA->id], 'has_packaging' => 0])->assertOk()->json();
        $this->assertEqualsWithDelta(40.0, $small['surcharge'], 0.001);
        $this->assertEqualsWithDelta(16.0, $small['shipping'], 0.001);
        $this->assertEqualsWithDelta(487.0, $small['taxable'], 0.001);
    }
}
