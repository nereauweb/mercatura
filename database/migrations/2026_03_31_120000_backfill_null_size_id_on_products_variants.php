<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products_variants')->whereNull('size_id')->update(['size_id' => 52]);
    }

    public function down(): void
    {
        // Irreversible: cannot distinguish rows that were NULL before backfill from legitimate use of size 52.
    }
};
