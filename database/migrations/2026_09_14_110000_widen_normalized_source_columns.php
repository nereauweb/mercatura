<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The normalized layer is the contract between the core and any connector:
 * its source columns cannot be enums of the suppliers known so far.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `normalized_products` MODIFY `source` VARCHAR(32) NOT NULL');
        DB::statement('ALTER TABLE `categories_import_aliases` MODIFY `source` VARCHAR(32) NOT NULL');
    }

    public function down(): void
    {
        // The columns stay strings: the original enums named the suppliers of one installation.
    }
};
