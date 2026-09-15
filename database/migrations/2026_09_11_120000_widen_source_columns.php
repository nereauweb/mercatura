<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The product and printing "source" columns were enums of supplier names.
 * A connector key is a string: widen them so demo data ("own") and future
 * connectors need no schema change. Existing values are preserved. The
 * printing links to the import staging tables become nullable: products
 * that are not imported have no normalized row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `products` MODIFY `source` VARCHAR(16) NOT NULL DEFAULT 'own'");
        DB::statement("ALTER TABLE `products_variants` MODIFY `source` VARCHAR(16) NOT NULL DEFAULT 'OWN'");
        DB::statement("ALTER TABLE `printing_variants` MODIFY `source` VARCHAR(16) NOT NULL DEFAULT 'own'");
        DB::statement('ALTER TABLE `printing_variants` MODIFY `normalized_product_id` BIGINT UNSIGNED NULL, MODIFY `normalized_variant_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // The columns stay strings: the original enums named the suppliers of one installation.
        DB::statement('ALTER TABLE `printing_variants` MODIFY `normalized_product_id` BIGINT UNSIGNED NOT NULL, MODIFY `normalized_variant_id` BIGINT UNSIGNED NOT NULL');
    }
};
