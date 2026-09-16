<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v2c.6: a customization received from an import can be protected from later imports (update and delete). Manual rows always are. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customizations', 'locked')) {
            Schema::table('customizations', fn (Blueprint $t) => $t->boolean('locked')->default(false)->after('family'));
        }
    }

    public function down(): void
    {
        Schema::table('customizations', fn (Blueprint $t) => $t->dropColumn('locked'));
    }
};
