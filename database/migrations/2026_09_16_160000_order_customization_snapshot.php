<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v2c.3 (docs/03_CUSTOMIZATIONS.md §4.5): an order keeps what was sold, not a
 * pointer into the supplier's price table. Each customization row of an
 * item snapshots technique, position, area, option and the amounts; the
 * fixed costs (setup, start) and the under-minimum surcharge become typed
 * rows of order_item_extras. Existing rows are backfilled from the live
 * option where it still exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_customizations', function (Blueprint $t): void {
            $t->unsignedBigInteger('option_id')->nullable()->change();
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
        });
        Schema::table('order_item_extras', function (Blueprint $t): void {
            if (! Schema::hasColumn('order_item_extras', 'type')) {
                $t->string('type', 16)->default('other')->after('item_id');
            }
            if (! Schema::hasColumn('order_item_extras', 'customization_id')) {
                $t->unsignedBigInteger('customization_id')->nullable()->after('type');
            }
            if (DB::select('SHOW INDEX FROM `order_item_extras` WHERE Key_name = ?', ['order_item_extras_item_id_index']) === []) {
                $t->index('item_id');
            }
        });

        // Backfill from the live option (technique, position, area, option, colours); amounts stay unknown for old rows.
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

    public function down(): void
    {
        Schema::table('order_item_extras', fn (Blueprint $t) => $t->dropColumn(['type', 'customization_id']));
        Schema::table('order_item_customizations', function (Blueprint $t): void {
            $t->dropColumn(['family', 'technique_label', 'position_label', 'area_label', 'option_label', 'number_of_colors', 'quantity', 'price', 'packaging_price']);
        });
    }
};
