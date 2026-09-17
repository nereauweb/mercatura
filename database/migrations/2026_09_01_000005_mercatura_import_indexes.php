<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mercatura over the monolith schema, part 5: the indexes the import needs.
 * Three legacy indexes were declared on `id` instead of the column they are
 * named after; the lookups of the import (and of the storefront listings)
 * had no index at all. Additive and guarded: safe on the dump and on an
 * installation migrated before this file existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Declared on `id` by the monolith: move them to the column they are named after.
        $this->replaceIfOn('normalized_products_variants', 'product_id_index', ['id'], ['product_id']);
        $this->replaceIfOn('products_variants', 'sku_index', ['id'], ['sku']);
        $this->replaceIfOn('product_sizes', 'type_id_index', ['id'], ['type_id']);

        $this->ensure('products', 'products_source_source_sku_index', ['source', 'source_sku']);
        $this->ensure('products_variants', 'products_variants_source_sku_index', ['source_sku']);
        $this->ensure('normalized_products', 'normalized_products_source_id_index', ['source_id']);
        $this->ensure('normalized_products', 'normalized_products_last_seen_active_index', ['last_seen_active']);
        $this->ensure('normalized_products_variants', 'normalized_products_variants_last_seen_active_index', ['last_seen_active']);
        $this->ensure('category_product', 'category_product_product_id_index', ['product_id']);
        $this->ensure('category_product', 'category_product_category_id_index', ['category_id']);
        $this->ensure('categories_import_aliases', 'categories_import_aliases_lookup_index', ['source', 'parent_category_ref', 'category_ref']);
        $this->ensure('product_colors', 'product_colors_label_index', ['label']);
        $this->ensure('product_sizes', 'product_sizes_label_index', ['label']);
        $this->ensure('import_logs', 'import_logs_import_id_index', ['import_id(64)']);
        $this->ensure('import_logs', 'import_logs_context_created_at_index', ['context(64)', 'created_at']);
        // Customization lookups of the connectors that do not filter by pipeline, and of the ones keyed on the supplier codes.
        $this->ensure('customizations', 'customizations_variant_labels_index', ['source', 'source_variant_sku', 'technique_label(96)', 'position_label(96)']);
        $this->ensure('customizations', 'customizations_supplier_codes_index', ['source', 'pipeline', 'source_product_sku', 'source_variant_sku', 'position_code', 'technique_main_code']);
    }

    public function down(): void
    {
        // The baseline dump is the way back: these migrations only move forward.
    }

    /** @param  list<string>  $columns  column names, optionally with a prefix length: "label(96)" */
    private function ensure(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexColumns($table, $index) !== []) {
            return;
        }
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, (string) preg_replace('/\(\d+\)$/', '', $column))) {
                return;
            }
        }
        $list = implode(', ', array_map(fn (string $c): string => preg_match('/^(\w+)\((\d+)\)$/', $c, $m) ? "`{$m[1]}`({$m[2]})" : "`{$c}`", $columns));
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$list})");
    }

    /**
     * @param  list<string>  $wrong
     * @param  list<string>  $columns
     */
    private function replaceIfOn(string $table, string $index, array $wrong, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $current = $this->indexColumns($table, $index);
        if ($current === $wrong) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
            $current = [];
        }
        if ($current === []) {
            $this->ensure($table, $index, $columns);
        }
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select('SELECT COLUMN_NAME AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX', [$table, $index]);

        return array_map(fn (object $row): string => (string) $row->c, $rows);
    }
};
