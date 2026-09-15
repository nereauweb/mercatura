<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v2c.0 defects (docs/03_CUSTOMIZATIONS.md §2.4): the printing_variants
 * "variant_id_index" indexed the primary key (4); order_items.unit_price was
 * written by the checkout but the column never existed (10); product_markups
 * was seeded and read by nobody, the live bands are
 * normalized_rules_pricing_products (9).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexColumns('printing_variants', 'variant_id_index') === ['id']) {
            Schema::table('printing_variants', fn (Blueprint $table) => $table->dropIndex('variant_id_index'));
        }
        if ($this->indexColumns('printing_variants', 'variant_id_index') === []) {
            Schema::table('printing_variants', fn (Blueprint $table) => $table->index('variant_id', 'variant_id_index'));
        }
        if (! Schema::hasColumn('order_items', 'unit_price')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->decimal('unit_price', 8, 2)->nullable()->after('price'));
        }
        Schema::dropIfExists('product_markups');
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'unit_price')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('unit_price'));
        }
        // The index stays correct and product_markups is not recreated: nothing reads it.
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        usort($rows, fn ($a, $b) => $a->Seq_in_index <=> $b->Seq_in_index);

        return array_map(fn ($row) => (string) $row->Column_name, $rows);
    }
};
