<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mercatura over the monolith schema, part 3 of 3: customizations
 * (docs/03_CUSTOMIZATIONS.md).
 *
 * The printing tables become the customization tables, same tree, generic
 * names: customizations → customization_areas → customization_options →
 * customization_tiers. Before the rename the inherited printing_variants
 * gains the pipeline columns and its unique key includes the pipeline (a
 * connector may keep several price lists side by side). Then: family,
 * locked and supplier_data added, never-read columns dropped, the "default
 * print" denormalisations renamed, the order snapshot
 * (order_item_customizations, typed order_item_extras) and the quotation
 * item's customization text.
 */
return new class extends Migration
{
    private const TABLES = [
        'printing_variants' => 'customizations',
        'printing_variants_sizes' => 'customization_areas',
        'printing_variants_colors' => 'customization_options',
        'printing_variants_prices' => 'customization_tiers',
        'order_item_printings' => 'order_item_customizations',
    ];

    private const RENAMED_COLUMNS = [
        'products' => ['default_print_technique' => 'default_customization_technique', 'default_print_position' => 'default_customization_position'],
        'normalized_products' => ['default_print_technique' => 'default_customization_technique', 'default_print_position' => 'default_customization_position', 'default_print_dimension' => 'default_customization_dimension', 'default_print_max_colors' => 'default_customization_max_colors'],
        'normalized_products_variants' => ['printing_default_technique' => 'customization_default_technique', 'printing_default_location' => 'customization_default_location', 'printing_default_dimension' => 'customization_default_dimension', 'printing_default_max_colors' => 'customization_default_max_colors'],
        'order_item_customizations' => ['printing_variant_color_id' => 'option_id', 'printing_label' => 'label', 'print_file' => 'file'],
        'quotations_items' => ['printing' => 'customization'],
    ];

    private const DROPPED_COLUMNS = [
        'customizations' => ['deleted_at'],
        'customization_areas' => ['deleted_at'],
        'customization_options' => ['deleted_at'],
        'customization_tiers' => ['price_method_1', 'price_method_2', 'packaging_price_method_1', 'packaging_price_method_2'],
    ];

    public function up(): void
    {
        $this->pipelineColumns();
        $this->renameTables();
        $this->renameColumns();
        $this->dropColumns();
        $this->customizationColumns();
        $this->orderSnapshot();
        $this->quotationItems();
    }

    public function down(): void
    {
        // The baseline dump is the way back: these migrations only move forward.
    }

    /** Pipeline, codes and packaging code on the inherited table, and the unique key that includes the pipeline. */
    private function pipelineColumns(): void
    {
        $table = Schema::hasTable('customizations') ? 'customizations' : 'printing_variants';
        if (! Schema::hasTable($table)) {
            throw new RuntimeException('Neither customizations nor printing_variants exists: this migration upgrades an existing schema, a fresh installation starts from the schema dump.');
        }
        Schema::table($table, function (Blueprint $t) use ($table): void {
            if (! Schema::hasColumn($table, 'pipeline')) {
                $t->string('pipeline', 32)->nullable()->after('source');
            }
            if (! Schema::hasColumn($table, 'position_code')) {
                $t->string('position_code', 16)->nullable()->after('position_label');
            }
            if (! Schema::hasColumn($table, 'technique_main_code')) {
                $t->string('technique_main_code', 16)->nullable()->after('position_code');
            }
            if (! Schema::hasColumn($table, 'max_print_position')) {
                $t->unsignedTinyInteger('max_print_position')->nullable()->after('max_colors');
            }
            if (! Schema::hasColumn($table, 'packaging_code')) {
                $t->string('packaging_code', 16)->nullable()->after('max_print_position');
            }
            if (! $this->hasIndex($table, 'printing_variants_source_pipeline_idx')) {
                $t->index(['source', 'pipeline'], 'printing_variants_source_pipeline_idx');
            }
        });
        // No row may stay NULL: a UNIQUE that includes a nullable pipeline would weaken uniqueness.
        // Existing rows get their source as pipeline; a connector with several pipelines renames its own rows.
        DB::table($table)->whereNull('pipeline')->update(['pipeline' => DB::raw('LOWER(`source`)')]);
        $expected = ['source', 'pipeline', 'source_product_sku', 'source_variant_sku', 'technique_label', 'position_label'];
        if ($this->indexColumns($table, 'process_v2_index') !== $expected) {
            if ($this->indexColumns($table, 'process_v2_index') !== []) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `process_v2_index`");
            }
            // HASH, not BTREE: the two varchar(512) utf8mb4 labels exceed the BTREE key limit.
            DB::statement("ALTER TABLE `{$table}` ADD UNIQUE KEY `process_v2_index` (`source`,`pipeline`,`source_product_sku`,`source_variant_sku`,`technique_label`,`position_label`) USING HASH");
        }
    }

    private function renameTables(): void
    {
        foreach (self::TABLES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    private function renameColumns(): void
    {
        foreach (self::RENAMED_COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($table, $columns): void {
                foreach ($columns as $old => $new) {
                    if (Schema::hasColumn($table, $old) && ! Schema::hasColumn($table, $new)) {
                        $t->renameColumn($old, $new);
                    }
                }
            });
        }
    }

    private function dropColumns(): void
    {
        foreach (self::DROPPED_COLUMNS as $table => $columns) {
            $present = array_values(array_filter($columns, fn (string $c): bool => Schema::hasColumn($table, $c)));
            if ($present !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
            }
        }
    }

    private function customizationColumns(): void
    {
        Schema::table('customizations', function (Blueprint $t): void {
            if (! Schema::hasColumn('customizations', 'family')) {
                $t->string('family', 32)->nullable()->after('pipeline')->index();
            }
            if (! Schema::hasColumn('customizations', 'locked')) {
                $t->boolean('locked')->default(false)->after('family');
            }
            if (! Schema::hasColumn('customizations', 'supplier_data')) {
                $t->json('supplier_data')->nullable()->after('packaging_code');
            }
        });
        Schema::table('customization_areas', function (Blueprint $t): void {
            if (! Schema::hasColumn('customization_areas', 'supplier_data')) {
                $t->json('supplier_data')->nullable()->after('height_mm');
            }
        });
    }

    /** What was sold, per option chosen on the item; the fixed costs as typed extras. */
    private function orderSnapshot(): void
    {
        Schema::table('order_item_customizations', function (Blueprint $t): void {
            if ($this->isNotNullable('order_item_customizations', 'option_id')) {
                $t->unsignedBigInteger('option_id')->nullable()->change();
            }
            foreach (['family' => 32, 'technique_label' => 512, 'position_label' => 512, 'area_label' => 128, 'option_label' => 64] as $column => $length) {
                if (! Schema::hasColumn('order_item_customizations', $column)) {
                    $t->string($column, $length)->nullable()->after('option_id');
                }
            }
            if (! Schema::hasColumn('order_item_customizations', 'number_of_colors')) {
                $t->integer('number_of_colors')->nullable()->after('option_label');
            }
            if (! Schema::hasColumn('order_item_customizations', 'quantity')) {
                $t->integer('quantity')->nullable()->after('number_of_colors');
            }
            if (! Schema::hasColumn('order_item_customizations', 'price')) {
                $t->decimal('price', 10, 2)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('order_item_customizations', 'packaging_price')) {
                $t->decimal('packaging_price', 10, 2)->nullable()->after('price');
            }
            if (! $this->hasIndex('order_item_customizations', 'order_item_customizations_item_id_index')) {
                $t->index('item_id');
            }
        });
        Schema::table('order_item_extras', function (Blueprint $t): void {
            if (! Schema::hasColumn('order_item_extras', 'type')) {
                $t->string('type', 16)->default('other')->after('item_id');
            }
            if (! Schema::hasColumn('order_item_extras', 'customization_id')) {
                $t->unsignedBigInteger('customization_id')->nullable()->after('type');
            }
            if (! $this->hasIndex('order_item_extras', 'order_item_extras_item_id_index')) {
                $t->index('item_id');
            }
        });
        // Backfill old rows from the live option; amounts stay unknown for them.
        DB::statement(<<<'SQL'
            UPDATE order_item_customizations oic
            JOIN customization_options o ON o.id = oic.option_id
            JOIN customization_areas a ON a.id = o.parent_id
            JOIN customizations c ON c.id = a.parent_id
            JOIN order_items i ON i.id = oic.item_id
            SET oic.family = c.family, oic.technique_label = c.technique_label, oic.position_label = c.position_label,
                oic.area_label = a.label, oic.option_label = o.label, oic.number_of_colors = o.number_of_colors, oic.quantity = i.quantity
            WHERE oic.technique_label IS NULL
        SQL);
    }

    private function quotationItems(): void
    {
        if (str_contains($this->columnDefinition('quotations_items', 'customization'), 'varchar(32)') || $this->isNotNullable('quotations_items', 'customization')) {
            Schema::table('quotations_items', fn (Blueprint $t) => $t->string('customization', 255)->nullable()->default(null)->change());
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::select('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1', [$table, $index]) !== [];
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select('SELECT COLUMN_NAME AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX', [$table, $index]);

        return array_map(fn ($row) => (string) $row->c, $rows);
    }

    private function isNotNullable(string $table, string $column): bool
    {
        $row = DB::selectOne('SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);

        return $row !== null && $row->n === 'NO';
    }

    private function columnDefinition(string $table, string $column): string
    {
        $row = DB::selectOne('SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);

        return $row ? strtolower((string) $row->t) : '';
    }
};
