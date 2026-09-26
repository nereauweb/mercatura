<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle of the customizations received from the connectors
 * (docs/03_CUSTOMIZATIONS.md §7): `last_seen_at` is the last import that
 * listed the row in the source, `active` is switched off by
 * cleanup:customizations when the source stopped listing it and back on when
 * it reappears, `source_hash` lets a connector skip a variant whose supplier
 * data did not change. Existing rows start as seen at their last update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customizations', function (Blueprint $t): void {
            if (! Schema::hasColumn('customizations', 'active')) {
                $t->boolean('active')->default(true)->after('locked')->index('customizations_active_idx');
            }
            if (! Schema::hasColumn('customizations', 'last_seen_at')) {
                $t->dateTime('last_seen_at')->nullable()->after('active')->index('customizations_last_seen_idx');
            }
            if (! Schema::hasColumn('customizations', 'source_hash')) {
                $t->string('source_hash', 64)->nullable()->after('last_seen_at');
            }
        });
        DB::table('customizations')->whereNull('last_seen_at')->update(['last_seen_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('customizations', function (Blueprint $t): void {
            foreach (['active', 'last_seen_at', 'source_hash'] as $column) {
                if (Schema::hasColumn('customizations', $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }
};
