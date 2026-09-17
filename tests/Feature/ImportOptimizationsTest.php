<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProductMarkup;
use App\Support\Connectors\MarkupRules;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The import's markup lookups are served from memory and follow every change of the bands. */
final class ImportOptimizationsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_markup_bands_are_read_once_and_flushed_when_they_change(): void
    {
        $this->seed(CoreSeeder::class);
        $rules = $this->app->make(MarkupRules::class);
        $rules->flush();
        $first = $rules->percent(50.0, null, null);

        DB::enableQueryLog();
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame($first, $rules->percent(50.0, null, null));
            $rules->tiers(1.5);
        }
        $this->assertLessThanOrEqual(1, count(DB::getQueryLog()), 'twenty prices, at most the one read of the tier rules');
        DB::disableQueryLog();

        $band = $rules->rule(50.0);
        $this->assertInstanceOf(ProductMarkup::class, $band);
        $band->update(['value' => (float) $band->value + 7]);
        $this->assertSame($first + 7, $rules->percent(50.0, null, null), 'a saved band is picked up at once');
    }

    public function test_the_import_indexes_exist(): void
    {
        foreach ([['normalized_products_variants', 'product_id_index', 'product_id'], ['products_variants', 'sku_index', 'sku'], ['products', 'products_source_source_sku_index', 'source'], ['category_product', 'category_product_product_id_index', 'product_id']] as [$table, $index, $firstColumn]) {
            $row = DB::selectOne('SELECT COLUMN_NAME AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND SEQ_IN_INDEX = 1', [$table, $index]);
            $this->assertSame($firstColumn, $row?->c, "$table.$index");
        }
    }
}
