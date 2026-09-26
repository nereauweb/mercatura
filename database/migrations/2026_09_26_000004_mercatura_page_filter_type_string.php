<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CMS page filters gain "is_new" (App\Enums\PageFilterType). The inherited
 * column was a MySQL enum listing the old values; it becomes a plain string so
 * the enum in code is the only list to maintain.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages_contents')) {
            return;
        }
        $column = collect(DB::select("SHOW COLUMNS FROM pages_contents WHERE Field = 'filter_type'"))->first();
        if ($column && str_starts_with(strtolower((string) $column->Type), 'enum')) {
            DB::statement("ALTER TABLE pages_contents MODIFY filter_type VARCHAR(32) NULL DEFAULT 'product_id'");
        }
    }

    public function down(): void
    {
        // A string column serves the old values too; nothing to undo.
    }
};
