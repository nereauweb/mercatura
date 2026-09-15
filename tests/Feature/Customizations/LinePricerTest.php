<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use App\Support\Customizations\LinePricer;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** The typed result of the one pricing algorithm (docs/03_CUSTOMIZATIONS.md §4.3) that the controllers, the order and the seeder read. */
final class LinePricerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_priced_line_carries_every_component(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();

        $line = app(LinePricer::class)->price([[$f->a->id, 60], [$f->b->id, 40]], [$f->screenOneColorA->id, 999999999], true);

        $this->assertSame($f->product->id, $line->product->id);
        $this->assertSame(100, $line->quantity);
        $this->assertTrue($line->packaging);
        $this->assertEqualsWithDelta(1218.0, $line->price, 0.001);
        $this->assertEqualsWithDelta(10.0, $line->additionalCosts, 0.001);
        $this->assertEqualsWithDelta(267.96, $line->vat(), 0.001);
        $this->assertEqualsWithDelta(12.18, $line->unitPrice(), 0.001);
        $this->assertEqualsWithDelta(12.28, $line->unitPriceWithAdditionalCosts(), 0.001);
        $this->assertEqualsWithDelta(1495.96, $line->totalTaxedPrice(), 0.001);
        $this->assertSame(50, $line->minimumQuantity);
        $this->assertFalse($line->underMinimum());
        $this->assertSame(5, $line->processingDays);

        $this->assertCount(2, $line->articles);
        [$a, $b] = $line->articles;
        $this->assertSame([$f->a->id, 60, 8.0, 30.0, 10.4, 624.0, 6.0], [$a->variant->id, $a->quantity, $a->originalPrice, $a->markupPercent, $a->unitPrice, $a->price, $a->additionalCosts]);
        $this->assertSame([$f->b->id, 40, 416.0], [$b->variant->id, $b->quantity, $b->price]);
        $this->assertCount(1, $b->customizations, 'the unknown option id is ignored');
        $c = $b->customizations[0];
        $this->assertSame($f->screenOneColorA->id, $c->chosen->id);
        $this->assertSame($f->screenOneColorB->id, $c->option->id, 'resolved on the article\'s own variant');
        $this->assertEqualsWithDelta(0.78, $c->unitPrice, 0.001);
        $this->assertEqualsWithDelta(31.2, $c->price, 0.001);
        $this->assertEqualsWithDelta(0.65, (float) $c->packagingUnitPrice, 0.001);
        $this->assertEqualsWithDelta(26.0, $c->packagingPrice, 0.001);

        $this->assertCount(1, $line->customizations);
        $fixed = $line->customizations[0];
        $this->assertSame([5.0, 30.0, 1, 30.0, 50, 5], [$fixed->startCost, $fixed->setup, $fixed->setupMultiplier, $fixed->setupPrice, $fixed->minimumQuantity, $fixed->processingDays]);
    }

    public function test_setup_multiplier_zero_counts_as_one_and_the_surcharge_follows_config(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        config(['mercatura.pricing.under_minimum_surcharge' => 25]);

        $line = app(LinePricer::class)->price([[$f->a->id, 20]], [$f->embroideryOptionA->id], false);

        $this->assertSame(1, $line->customizations[0]->setupMultiplier);
        $this->assertEqualsWithDelta(60.0, $line->customizations[0]->setupPrice, 0.001);
        $this->assertTrue($line->underMinimum());
        $this->assertEqualsWithDelta(25.0, $line->surcharge, 0.001);
        // 20 × 18.00 + print 20 × 1.80 + setup 60 + surcharge 25
        $this->assertEqualsWithDelta(481.0, $line->price, 0.001);
    }
}
