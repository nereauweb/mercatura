<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('printing_variants')) {
            throw new RuntimeException('Tabella printing_variants assente: abortisco, non creo lo schema da zero.');
        }

        $needsColumnsOrFilterIndex =
            ! Schema::hasColumn('printing_variants', 'pipeline')
            || ! Schema::hasColumn('printing_variants', 'position_code')
            || ! Schema::hasColumn('printing_variants', 'technique_main_code')
            || ! Schema::hasColumn('printing_variants', 'max_print_position')
            || ! Schema::hasColumn('printing_variants', 'packaging_code')
            || ! $this->hasIndex('printing_variants', 'printing_variants_source_pipeline_idx');

        if ($needsColumnsOrFilterIndex) {
            Schema::table('printing_variants', function (Blueprint $table) {
                if (! Schema::hasColumn('printing_variants', 'pipeline')) {
                    $table->string('pipeline', 32)->nullable()->after('source');
                }
                if (! Schema::hasColumn('printing_variants', 'position_code')) {
                    $table->string('position_code', 16)->nullable()->after('position_label');
                }
                if (! Schema::hasColumn('printing_variants', 'technique_main_code')) {
                    $table->string('technique_main_code', 16)->nullable()->after('position_code');
                }
                if (! Schema::hasColumn('printing_variants', 'max_print_position')) {
                    $table->unsignedTinyInteger('max_print_position')->nullable()->after('max_colors');
                }
                if (! Schema::hasColumn('printing_variants', 'packaging_code')) {
                    $table->string('packaging_code', 16)->nullable()->after('max_print_position');
                }
                if (! $this->hasIndex('printing_variants', 'printing_variants_source_pipeline_idx')) {
                    $table->index(['source', 'pipeline'], 'printing_variants_source_pipeline_idx');
                }
            });
        }

        // No row may stay NULL: a UNIQUE that includes a nullable pipeline would
        // weaken uniqueness (MariaDB treats NULL as distinct). Existing rows get
        // their source as pipeline; a connector that keeps several pipelines
        // renames its own rows in its package migration.
        DB::table('printing_variants')
            ->whereNull('pipeline')
            ->update(['pipeline' => DB::raw('LOWER(`source`)')]);

        $this->rebuildProcessV2UniqueIndex(includePipeline: true);
    }

    public function down(): void
    {
        $this->rebuildProcessV2UniqueIndex(includePipeline: false);

        Schema::table('printing_variants', function (Blueprint $table) {
            if ($this->hasIndex('printing_variants', 'printing_variants_source_pipeline_idx')) {
                $table->dropIndex('printing_variants_source_pipeline_idx');
            }
            $drop = [];
            foreach (['pipeline', 'position_code', 'technique_main_code', 'max_print_position', 'packaging_code'] as $column) {
                if (Schema::hasColumn('printing_variants', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    /**
     * Live unique is HASH (not BTREE): technique_label/position_label are varchar(512)
     * utf8mb4, so a BTREE unique exceeds the 3072-byte key limit.
     */
    private function rebuildProcessV2UniqueIndex(bool $includePipeline): void
    {
        $columns = $includePipeline
            ? '`source`,`pipeline`,`source_product_sku`,`source_variant_sku`,`technique_label`,`position_label`'
            : '`source`,`source_product_sku`,`source_variant_sku`,`technique_label`,`position_label`';

        $current = $this->indexColumns('printing_variants', 'process_v2_index');
        $expected = $includePipeline
            ? ['source', 'pipeline', 'source_product_sku', 'source_variant_sku', 'technique_label', 'position_label']
            : ['source', 'source_product_sku', 'source_variant_sku', 'technique_label', 'position_label'];

        if ($current === $expected) {
            return;
        }

        if ($current !== []) {
            DB::statement('ALTER TABLE `printing_variants` DROP INDEX `process_v2_index`');
        }

        DB::statement(
            'ALTER TABLE `printing_variants` ADD UNIQUE KEY `process_v2_index` ('.$columns.') USING HASH'
        );
    }

    private function hasIndex(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        );

        return $rows !== [];
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
            [$table, $index]
        );

        return array_map(static fn ($row) => $row->COLUMN_NAME, $rows);
    }
};
