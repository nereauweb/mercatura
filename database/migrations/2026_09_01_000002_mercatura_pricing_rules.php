<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mercatura over the monolith schema, part 2 of 3: pricing rules.
 *
 * The markup bands (once normalized_rules_pricing_products, named after the
 * import step that first used them) become product_markups: one set of
 * bands "from_condition < order value <= to_condition → value %", used by
 * the import on the tier quantities and by the cart on the bought quantity.
 * The parallel series (condition_3, a supplier's web-shop subtype) and the
 * two constant columns are dropped. The quantity breaks used when a supplier
 * gives none become normalized_tiers_rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('normalized_rules_pricing_products') && ! Schema::hasTable('product_markups')) {
            Schema::rename('normalized_rules_pricing_products', 'product_markups');
        }
        if (Schema::hasColumn('product_markups', 'condition_3')) {
            DB::table('product_markups')->where('condition_3', '<>', 0)->delete();
        }
        $drop = array_values(array_filter(['condition_3', 'condition_type', 'delta_type'], fn (string $c): bool => Schema::hasColumn('product_markups', $c)));
        if ($drop !== []) {
            Schema::table('product_markups', fn (Blueprint $table) => $table->dropColumn($drop));
        }
        Schema::table('product_markups', function (Blueprint $table): void {
            if (Schema::hasColumn('product_markups', 'condition_1')) {
                $table->renameColumn('condition_1', 'from_condition');
            }
            if (Schema::hasColumn('product_markups', 'condition_2')) {
                $table->renameColumn('condition_2', 'to_condition');
            }
        });

        if (Schema::hasTable('normalized_rules_price_tiers') && ! Schema::hasTable('normalized_tiers_rules')) {
            Schema::rename('normalized_rules_price_tiers', 'normalized_tiers_rules');
        }
    }

    public function down(): void
    {
        // The baseline dump is the way back: these migrations only move forward.
    }
};
