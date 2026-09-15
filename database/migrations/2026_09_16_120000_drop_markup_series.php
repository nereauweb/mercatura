<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One set of markup bands. The second series (condition_3 = 1, once meant
 * for a supplier's web-shop subtype) carried the same values everywhere and
 * nothing selects it any more; overrides, if ever needed, will be designed
 * as such (per category or criteria), not as a parallel series.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_markups', 'condition_3')) {
            return;
        }
        DB::table('product_markups')->where('condition_3', '<>', 0)->delete();
        Schema::table('product_markups', fn (Blueprint $table) => $table->dropColumn('condition_3'));
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_markups', 'condition_3')) {
            Schema::table('product_markups', fn (Blueprint $table) => $table->integer('condition_3')->default(0)->after('condition_2'));
        }
    }
};
