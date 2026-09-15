<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** The option chosen on one variant is re-resolved on every article of the line by technique, position, size and option label. */
final class SiblingLookupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_equivalent_option_on_another_variant_and_fallback_to_self(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();

        $this->assertSame($f->screenOneColorB->id, $f->screenOneColorA->equivalentFor($f->b->id)->id);
        $this->assertSame($f->screenOneColorA->id, $f->screenOneColorB->equivalentFor($f->a->id)->id);
        $this->assertSame($f->screenOneColorA->id, $f->screenOneColorA->equivalentFor($f->a->id)->id, 'own variant');
        $this->assertSame($f->embroideryOptionA->id, $f->embroideryOptionA->equivalentFor($f->b->id)->id, 'no embroidery on B: the option itself is used');
        $this->assertSame($f->screenOneColorA->id, $f->screenOneColorA->equivalentFor(999999999)->id, 'unknown variant: the option itself');
    }

    public function test_print_price_tier_follows_the_line_quantity_not_the_article_quantity(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();

        $price = $f->screenOneColorA->priceFor(100, 40, true, false, 30.0);
        $this->assertEqualsWithDelta(['unit_price' => 0.78, 'quantity' => 40, 'price' => 31.2, 'packaging_quantity' => 40, 'packaging_unit_price' => 0.65, 'packaging_price' => 26.0], $price, 0.0001);

        $original = $f->screenOneColorA->priceFor(100, 40, true, true);
        $this->assertEqualsWithDelta(['unit_price' => 0.6, 'quantity' => 40, 'price' => 24.0, 'packaging_quantity' => 40, 'packaging_unit_price' => 0.5, 'packaging_price' => 20.0], $original, 0.0001);

        $stored = $f->screenOneColorA->priceFor(10, 10);
        $this->assertSame(9.99, $stored['unit_price'], 'without a markup the stored (import-time) price is used');

        $this->assertSame('FRONTE - Serigrafia  10x10 1 colore', $f->screenOneColorA->fullLabel());
        $this->assertSame('2 colori', $f->screenTwoColorsA->label());
        $this->assertSame('Fino a 12', $f->embroideryOptionA->label());
    }
}
