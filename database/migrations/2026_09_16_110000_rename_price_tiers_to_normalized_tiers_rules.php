<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** The quantity-break rules used at import when a supplier gives no tiers: shorter name, same role. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('normalized_rules_price_tiers') && ! Schema::hasTable('normalized_tiers_rules')) {
            Schema::rename('normalized_rules_price_tiers', 'normalized_tiers_rules');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('normalized_tiers_rules') && ! Schema::hasTable('normalized_rules_price_tiers')) {
            Schema::rename('normalized_tiers_rules', 'normalized_rules_price_tiers');
        }
    }
};
