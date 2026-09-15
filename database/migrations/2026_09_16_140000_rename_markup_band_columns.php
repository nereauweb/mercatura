<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A band is "from_condition < order value <= to_condition → value %": say so in the column names. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_markups', function (Blueprint $table): void {
            if (Schema::hasColumn('product_markups', 'condition_1')) {
                $table->renameColumn('condition_1', 'from_condition');
            }
            if (Schema::hasColumn('product_markups', 'condition_2')) {
                $table->renameColumn('condition_2', 'to_condition');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_markups', function (Blueprint $table): void {
            if (Schema::hasColumn('product_markups', 'from_condition')) {
                $table->renameColumn('from_condition', 'condition_1');
            }
            if (Schema::hasColumn('product_markups', 'to_condition')) {
                $table->renameColumn('to_condition', 'condition_2');
            }
        });
    }
};
