<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Support\ProductPageData;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** Neutral and printed quantity bands on the product page (ProductPageData::priceTable). */
final class ProductPriceTableTest extends TestCase
{
    use DatabaseTransactions;

    public function test_price_table_includes_the_first_quantity_and_the_default_printing_row(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();

        $table = ProductPageData::priceTableRows($f->product, $f->a);

        $this->assertSame(['1+', '50+', '100+'], $table['columns']);
        $this->assertNotEmpty($table['printed_columns']);
        $this->assertNotEmpty($table['printed_rows']);
        $this->assertStringContainsString('Serigrafia', (string) $table['printed_note']);
        $this->assertStringContainsString('Fronte', (string) $table['printed_note']);
        $this->assertSame(50, $table['minCustomizationQuantity']);
    }
}
