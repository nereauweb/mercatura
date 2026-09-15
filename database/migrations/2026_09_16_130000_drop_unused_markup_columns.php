<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** condition_type and delta_type were always 0 and read by nobody: a band is a range and a percent. */
return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(['condition_type', 'delta_type'], fn (string $c): bool => Schema::hasColumn('product_markups', $c)));
        if ($columns !== []) {
            Schema::table('product_markups', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    public function down(): void
    {
        Schema::table('product_markups', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_markups', 'condition_type')) {
                $table->integer('condition_type')->default(0)->after('id');
            }
            if (! Schema::hasColumn('product_markups', 'delta_type')) {
                $table->integer('delta_type')->default(0)->after('condition_2');
            }
        });
    }
};
