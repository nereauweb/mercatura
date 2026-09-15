<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/**
 * Characterisation of the line pricing (docs/03_CUSTOMIZATIONS.md §4.3):
 * the configurator summary and the cart must keep producing these exact
 * numbers through every v2c phase. Expected values are computed by hand
 * from the fixture (CoreSeeder markup bands: 800 € → 30 %, 200 € → 80 %).
 */
final class LinePricingTest extends TestCase
{
    use DatabaseTransactions;

    private CustomizationFixture $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->f = CustomizationFixture::create();
    }

    /** @param  array<string, mixed>  $payload */
    private function summary(array $payload): array
    {
        return $this->postJson('/prodotti/configuratore/articoli', $payload)->assertOk()->json();
    }

    /** @param  array<string, array<string, mixed>>  $sessionCart */
    private function cart(array $sessionCart): array
    {
        return $this->withSession(['cart' => $sessionCart])->get('/carrello/riepilogo')->assertOk()->viewData('cart');
    }

    public function test_two_articles_one_print_with_packaging(): void
    {
        // 100 pieces: tier 100 → cost 8.00, band 100 × 8 = 800 → 30 %; unit 10.40.
        // Print tier 100 → 0.60 × 1.30 = 0.78; packaging 0.65 per piece (stored price, not re-marked-up).
        $payload = ['articles' => [[$this->f->a->id, 60], [$this->f->b->id, 40]], 'printings' => [$this->f->screenOneColorA->id], 'has_packaging' => 1];

        $summary = $this->summary($payload);
        $this->assertSame('1.218,00&nbsp;&euro;', $summary['total_price'], '624 + 416 + 46.80 + 39 + 31.20 + 26 + start 5 + setup 30');
        $this->assertSame('267,96&nbsp;&euro;', $summary['total_vat']);
        $this->assertSame('100', $summary['total_quantity']);
        $this->assertEqualsWithDelta(10.0, $summary['total_additional_costs_amount'], 0.001, '100 pieces × 0.10');
        $this->assertSame('10.00&nbsp;&euro;', $summary['total_additional_costs']);
        $this->assertSame('12,28&nbsp;&euro;', $summary['unit_price'], '(1218 + 10) / 100');
        $this->assertSame('1.495,96&nbsp;&euro;', $summary['total_taxed_price']);
        $this->assertSame('14,96&nbsp;&euro;', $summary['unit_taxed_price']);
        $lines = array_column($summary['lines'], 'column_1');
        $this->assertCount(8, $lines, 'article, print, packaging ×2, start, setup');
        $this->assertSame('FRONTE - Serigrafia  10x10 1 colore', $lines[1]);
        $this->assertSame('Confezionamento', $lines[2]);
        $this->assertSame('Avviamento', $lines[6]);
        $this->assertSame('Setup Serigrafia Fronte', $lines[7]);
        $this->assertSame('60x0,78&nbsp;&euro;', $summary['lines'][1]['column_2']);
        $this->assertSame('46,80&nbsp;&euro;', $summary['lines'][1]['column_3']);
        $this->assertSame('1x30,00', $summary['lines'][7]['column_2']);

        $cart = $this->cart(['line1' => $payload]);
        $item = $cart['items'][0];
        $this->assertEqualsWithDelta(1218.00, $item['price'], 0.001);
        $this->assertSame(100, $item['quantity']);
        $this->assertEqualsWithDelta(12.18, $item['unit_price'], 0.001, 'cart unit price excludes additional costs');
        $this->assertEqualsWithDelta(10.0, $item['additional_costs'], 0.001);
        $this->assertEqualsWithDelta(1218.00, $cart['items_price'], 0.001);
        $this->assertEqualsWithDelta(0.0, $cart['delivery_cost'], 0.001, 'free delivery above 500');
        $this->assertEqualsWithDelta(1218.00, $cart['total_price'], 0.001);
        $this->assertEqualsWithDelta(267.96, $cart['tax'], 0.001);
        $this->assertEqualsWithDelta(1495.96, $cart['total_taxed_price'], 0.001);
        $this->assertSame(17, $cart['delivery_days'], 'product 7 + default print 5, plus the print days 5');
        $this->assertCount(2, $item['articles']);
        $this->assertEqualsWithDelta(10.40, $item['articles'][1]['unit_price'], 0.001);
        $this->assertEqualsWithDelta(416.00, $item['articles'][1]['quantity_price'], 0.001);
        $this->assertCount(1, $item['printings']);
        $this->assertSame($this->f->screenOneColorA->id, $item['printings'][0]->id);
    }

    public function test_under_minimum_adds_the_flat_surcharge(): void
    {
        // 20 pieces: tier 1 → 10.00, band 200 → 80 % (band 150–200 wins: to_condition >= 200); unit 18.00.
        $payload = ['articles' => [[$this->f->a->id, 20]], 'printings' => [$this->f->screenOneColorA->id], 'has_packaging' => 0];

        $summary = $this->summary($payload);
        $this->assertSame('471,00&nbsp;&euro;', $summary['total_price'], '360 + print 36 + surcharge 40 + start 5 + setup 30');
        $this->assertSame('103,62&nbsp;&euro;', $summary['total_vat']);
        $this->assertSame('23,65&nbsp;&euro;', $summary['unit_price'], '(471 + 2) / 20');
        $this->assertSame('Sotto soglia minima (50 pz)', $summary['lines'][2]['column_1']);
        $this->assertSame('40,00&nbsp;&euro;', $summary['lines'][2]['column_3']);

        $cart = $this->cart(['line1' => $payload]);
        $this->assertEqualsWithDelta(471.00, $cart['items_price'], 0.001);
        $this->assertEqualsWithDelta(16.0, $cart['delivery_cost'], 0.001);
        $this->assertEqualsWithDelta(487.00, $cart['total_price'], 0.001);
        $this->assertEqualsWithDelta(107.14, $cart['tax'], 0.001);
        $this->assertEqualsWithDelta(596.14, $cart['total_taxed_price'], 0.001, '487 + 107.14 + additional 2');
    }

    public function test_setup_multiplier_scales_the_setup(): void
    {
        $payload = ['articles' => [[$this->f->a->id, 100]], 'printings' => [$this->f->screenTwoColorsA->id], 'has_packaging' => 0];

        $summary = $this->summary($payload);
        $this->assertSame('1.178,00&nbsp;&euro;', $summary['total_price'], '1040 + print 78 + setup 30 × 2');
        $this->assertSame('2x30,00', end($summary['lines'])['column_2']);
        $this->assertSame('11,88&nbsp;&euro;', $summary['unit_price']);

        $cart = $this->cart(['line1' => $payload]);
        $this->assertEqualsWithDelta(1178.00, $cart['items_price'], 0.001);
    }

    public function test_two_prints_on_one_article_and_the_setup_multiplier_zero_asymmetry(): void
    {
        // docs/03_CUSTOMIZATIONS.md §2.4 defect 7, resolved in v2c.1 (decision 4): a
        // setup_multiplier of 0 counts as 1 everywhere, as the configurator always did.
        $payload = ['articles' => [[$this->f->a->id, 100]], 'printings' => [$this->f->screenOneColorA->id, $this->f->embroideryOptionA->id], 'has_packaging' => 0];

        $summary = $this->summary($payload);
        $this->assertSame('1.291,00&nbsp;&euro;', $summary['total_price'], '1040 + 78 + 78 + start 5 + setup 30 + setup 60 × 1');
        $lines = array_column($summary['lines'], 'column_1');
        $this->assertSame(['FIX-001-01', 'FRONTE - Serigrafia  10x10 1 colore', 'RETRO - Ricamo  8x8 Fino a 12', 'Avviamento', 'Setup Serigrafia Fronte', 'Setup Ricamo Retro'], array_map(fn (string $l): string => strip_tags(html_entity_decode(explode(' <span', $l)[0])), $lines));

        $cart = $this->cart(['line1' => $payload]);
        $this->assertEqualsWithDelta(1291.00, $cart['items_price'], 0.001, 'same total as the configurator');
        $this->assertSame(18, $cart['delivery_days'], 'product 12 + the longest print (embroidery 6)');
    }

    public function test_plain_goods_have_no_print_lines(): void
    {
        $payload = ['articles' => [[$this->f->a->id, 50]], 'printings' => [], 'has_packaging' => 0];

        $summary = $this->summary($payload);
        // 50 pieces: tier 50 → 9.00, band 450 → 50 % (band 400–450 wins: to_condition >= 450); unit 13.50.
        $this->assertSame('675,00&nbsp;&euro;', $summary['total_price']);
        $this->assertCount(1, $summary['lines']);

        $cart = $this->cart(['line1' => $payload]);
        $this->assertEqualsWithDelta(675.00, $cart['items_price'], 0.001);
        $this->assertSame([], $cart['items'][0]['printings']);
        $this->assertSame(12, $cart['delivery_days']);
    }

    public function test_a_print_option_of_a_dead_pipeline_is_ignored(): void
    {
        $registry = $this->app->make(\App\Support\ImportConnectors::class);
        $registry->register(new class extends \App\Support\Connectors\BaseConnector
        {
            public function key(): string
            {
                return 'own';
            }

            public function label(): string
            {
                return 'Own';
            }

            public function printingPipelines(): array
            {
                return ['other'];
            }
        });
        $payload = ['articles' => [[$this->f->a->id, 100]], 'printings' => [$this->f->screenOneColorA->id], 'has_packaging' => 0];

        $this->assertSame('1.040,00&nbsp;&euro;', $this->summary($payload)['total_price'], 'the print of a pipeline that is not live prices nothing');
        $this->assertEqualsWithDelta(1040.00, $this->cart(['line1' => $payload])['items_price'], 0.001);
    }
}
