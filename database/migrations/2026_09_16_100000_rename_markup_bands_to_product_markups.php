<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The markup bands were named after the import step that first used them
 * (normalized_rules_pricing_products); today they price every cart line and
 * every configurator summary too. The unused legacy product_markups table was
 * dropped by the previous migration, the name is reused for the live bands.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('normalized_rules_pricing_products') && ! Schema::hasTable('product_markups')) {
            Schema::rename('normalized_rules_pricing_products', 'product_markups');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_markups') && ! Schema::hasTable('normalized_rules_pricing_products')) {
            Schema::rename('product_markups', 'normalized_rules_pricing_products');
        }
    }
};
