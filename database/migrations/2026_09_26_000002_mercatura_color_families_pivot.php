<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A colour belongs to any number of colour families (a composite colour such
 * as "blu/bianco" sits in two). The one-family column becomes a pivot table;
 * existing links are copied, the column is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_colors_families_colors')) {
            Schema::create('product_colors_families_colors', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('family_id')->index('pcfc_family_idx');
                $t->unsignedBigInteger('color_id')->index('pcfc_color_idx');
                $t->timestamps();
                $t->unique(['family_id', 'color_id'], 'pcfc_family_color_unique');
            });
        }
        if (Schema::hasColumn('product_colors', 'family_id')) {
            $now = now();
            DB::table('product_colors')->whereNotNull('family_id')->orderBy('id')->chunk(500, function ($colors) use ($now): void {
                DB::table('product_colors_families_colors')->insertOrIgnore($colors->map(fn ($c) => [
                    'family_id' => $c->family_id, 'color_id' => $c->id, 'created_at' => $now, 'updated_at' => $now,
                ])->all());
            });
            Schema::table('product_colors', function (Blueprint $t): void {
                $t->dropColumn('family_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_colors', 'family_id')) {
            Schema::table('product_colors', function (Blueprint $t): void {
                $t->unsignedBigInteger('family_id')->nullable()->after('id');
            });
            foreach (DB::table('product_colors_families_colors')->orderBy('id')->get() as $link) {
                DB::table('product_colors')->where('id', $link->color_id)->whereNull('family_id')->update(['family_id' => $link->family_id]);
            }
        }
        Schema::dropIfExists('product_colors_families_colors');
    }
};
