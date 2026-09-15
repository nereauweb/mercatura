<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v2c.4: the quotation item's yes/no "printing" becomes a free `customization` text (docs/03 decision 11). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quotations_items', 'printing') && ! Schema::hasColumn('quotations_items', 'customization')) {
            Schema::table('quotations_items', fn (Blueprint $t) => $t->renameColumn('printing', 'customization'));
        }
        Schema::table('quotations_items', fn (Blueprint $t) => $t->string('customization', 255)->nullable()->default(null)->change());
    }

    public function down(): void
    {
        Schema::table('quotations_items', fn (Blueprint $t) => $t->renameColumn('customization', 'printing'));
    }
};
