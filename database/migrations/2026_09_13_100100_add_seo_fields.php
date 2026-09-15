<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-entity SEO fields of docs/ARCHITECTURE.md §12. seo_title and
 * seo_description keep their names (the legacy admin writes them);
 * the rest is new. Media alt lives in media.custom_properties.
 */
return new class extends Migration
{
    private const TABLES = ['products', 'categories', 'pages', 'blog_articles'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('canonical_url', 512)->nullable()->after('seo_description');
                $table->boolean('noindex')->default(false)->after('canonical_url');
                $table->string('og_title', 255)->nullable()->after('noindex');
                $table->text('og_description')->nullable()->after('og_title');
                $table->string('og_image', 512)->nullable()->after('og_description');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['canonical_url', 'noindex', 'og_title', 'og_description', 'og_image']);
            });
        }
    }
};
