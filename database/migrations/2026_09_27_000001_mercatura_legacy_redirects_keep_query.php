<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A redirect may forward the request's query string (listing filters, search terms) to its target. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('legacy_redirects', 'keep_query')) {
            Schema::table('legacy_redirects', function (Blueprint $t): void {
                $t->boolean('keep_query')->default(false)->after('status_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('legacy_redirects', 'keep_query')) {
            Schema::table('legacy_redirects', function (Blueprint $t): void {
                $t->dropColumn('keep_query');
            });
        }
    }
};
