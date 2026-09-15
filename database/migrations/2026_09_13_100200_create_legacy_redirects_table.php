<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy_redirects mechanism of docs/ARCHITECTURE.md §12: one 301 (or
 * 410) per old path, served only when nothing else matches (Handler 404),
 * never chained. Rows are added by installations (redirect map of the
 * platform being replaced) and automatically when an admin changes a slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path', 512);
            $table->string('to_path', 512)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
            $table->unique(['from_path'], 'legacy_redirects_from_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_redirects');
    }
};
