<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2c.2 (docs/03_CUSTOMIZATIONS.md §4.1): the printing tables become the
 * customization tables. Same tree, same columns, generic names; `family`
 * and `attributes` added, never-read columns dropped. Guarded so that a
 * database migrated from the monolith and a fresh one both end up here.
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

    private const COLUMNS = [
        'products' => ['default_print_technique' => 'default_customization_technique', 'default_print_position' => 'default_customization_position'],
        'normalized_products' => ['default_print_technique' => 'default_customization_technique', 'default_print_position' => 'default_customization_position', 'default_print_dimension' => 'default_customization_dimension', 'default_print_max_colors' => 'default_customization_max_colors'],
        'normalized_products_variants' => ['printing_default_technique' => 'customization_default_technique', 'printing_default_location' => 'customization_default_location', 'printing_default_dimension' => 'customization_default_dimension', 'printing_default_max_colors' => 'customization_default_max_colors'],
        'order_item_customizations' => ['printing_variant_color_id' => 'option_id', 'printing_label' => 'label', 'print_file' => 'file'],
    ];

    private const DROPPED = [
        'customizations' => ['deleted_at'],
        'customization_areas' => ['deleted_at'],
        'customization_options' => ['deleted_at'],
        'customization_tiers' => ['price_method_1', 'price_method_2', 'packaging_price_method_1', 'packaging_price_method_2'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($table, $columns): void {
                foreach ($columns as $old => $new) {
                    if (Schema::hasColumn($table, $old) && ! Schema::hasColumn($table, $new)) {
                        $t->renameColumn($old, $new);
                    }
                }
            });
        }
        foreach (self::DROPPED as $table => $columns) {
            $present = array_values(array_filter($columns, fn (string $c): bool => Schema::hasColumn($table, $c)));
            if ($present !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
            }
        }
        Schema::table('customizations', function (Blueprint $t): void {
            if (! Schema::hasColumn('customizations', 'family')) {
                $t->string('family', 32)->nullable()->after('pipeline')->index();
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
        Schema::table('order_item_customizations', function (Blueprint $t): void {
            if (! $this->hasIndex('order_item_customizations', 'order_item_customizations_item_id_index')) {
                $t->index('item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_item_customizations', fn (Blueprint $t) => $t->dropIndex('order_item_customizations_item_id_index'));
        Schema::table('customization_areas', fn (Blueprint $t) => $t->dropColumn('supplier_data'));
        Schema::table('customizations', fn (Blueprint $t) => $t->dropColumn(['family', 'supplier_data']));
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns): void {
                foreach ($columns as $old => $new) {
                    $t->renameColumn($new, $old);
                }
            });
        }
        foreach (array_reverse(self::TABLES) as $old => $new) {
            Schema::rename($new, $old);
        }
        // The dropped columns are not recreated: nothing read them.
    }

    private function hasIndex(string $table, string $index): bool
    {
        return \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== [];
    }
};
