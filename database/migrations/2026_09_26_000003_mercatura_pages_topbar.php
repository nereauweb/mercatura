<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A CMS page can be linked from the header's utility bar (next to Contacts), not only from the category bar (`navbar`). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'topbar')) {
            Schema::table('pages', function (Blueprint $t): void {
                $t->boolean('topbar')->default(false)->after('navbar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pages', 'topbar')) {
            Schema::table('pages', function (Blueprint $t): void {
                $t->dropColumn('topbar');
            });
        }
    }
};
