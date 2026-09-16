<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Mercatura over the monolith schema, part 1 of 3: catalogue and SEO.
 *
 * The schema dump (database/schema/mysql-schema.sql) is the baseline of a
 * fresh installation; these three migrations exist for databases migrated
 * from the platform the core was bootstrapped from, so every step checks
 * what is already there and can be re-run. In order: brands as a table,
 * per-entity SEO fields (ARCHITECTURE §12), legacy_redirects, source
 * columns as strings instead of enums of supplier names (§13), and three
 * fixes of the inherited schema (a mis-targeted index, a column the
 * checkout wrote but never existed, a markup table nobody read).
 */
return new class extends Migration
{
    private const SEO_TABLES = ['products', 'categories', 'pages', 'blog_articles'];

    public function up(): void
    {
        $this->brands();
        $this->seoFields();
        $this->legacyRedirects();
        $this->sourceColumns();
        $this->fixes();
    }

    public function down(): void
    {
        // The baseline dump is the way back: these migrations only move forward.
    }

    private function brands(): void
    {
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 64)->unique();
                $table->string('slug', 96)->unique();
                $table->string('logo', 255)->nullable();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->string('seo_title', 70)->nullable();
                $table->string('seo_description', 300)->nullable();
                $table->string('canonical_url', 512)->nullable();
                $table->boolean('noindex')->default(false);
                $table->string('og_title', 255)->nullable();
                $table->text('og_description')->nullable();
                $table->string('og_image', 512)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasColumn('products', 'brand_id')) {
            Schema::table('products', fn (Blueprint $table) => $table->foreignId('brand_id')->nullable()->after('brand')->constrained('brands')->nullOnDelete());
        }
        if (DB::table('brands')->count() > 0 || DB::table('products')->whereNotNull('brand_id')->exists()) {
            return;
        }
        // Backfill from the denormalised labels; empty, "0" and "Unbranded" mean no brand.
        $labels = DB::table('products')->whereNotNull('brand')->whereNotIn('brand', ['', '0', 'Unbranded'])
            ->select('brand', DB::raw('MAX(brand_image) as logo'))->groupBy('brand')->orderBy('brand')->get();
        $position = 0;
        $slugs = [];
        foreach ($labels as $row) {
            $base = Str::slug($row->brand) ?: 'brand-'.substr(md5($row->brand), 0, 8);
            $slug = $base;
            for ($n = 2; isset($slugs[$slug]); $n++) {
                $slug = $base.'-'.$n;
            }
            $slugs[$slug] = true;
            $id = DB::table('brands')->insertGetId([
                'name' => $row->brand, 'slug' => $slug, 'logo' => $row->logo ? '/img/brands/'.$row->logo : null,
                'position' => $position++, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('products')->where('brand', $row->brand)->update(['brand_id' => $id]);
        }
    }

    private function seoFields(): void
    {
        foreach (self::SEO_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                if (! Schema::hasColumn($name, 'canonical_url')) {
                    $table->string('canonical_url', 512)->nullable()->after('seo_description');
                }
                if (! Schema::hasColumn($name, 'noindex')) {
                    $table->boolean('noindex')->default(false)->after('canonical_url');
                }
                if (! Schema::hasColumn($name, 'og_title')) {
                    $table->string('og_title', 255)->nullable()->after('noindex');
                }
                if (! Schema::hasColumn($name, 'og_description')) {
                    $table->text('og_description')->nullable()->after('og_title');
                }
                if (! Schema::hasColumn($name, 'og_image')) {
                    $table->string('og_image', 512)->nullable()->after('og_description');
                }
            });
        }
    }

    private function legacyRedirects(): void
    {
        if (Schema::hasTable('legacy_redirects')) {
            return;
        }
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

    /** Enums of supplier names become strings: a connector key is a string, and demo data uses "own". */
    private function sourceColumns(): void
    {
        $customizations = Schema::hasTable('customizations') ? 'customizations' : 'printing_variants';
        $statements = [
            'products' => "MODIFY `source` VARCHAR(16) NOT NULL DEFAULT 'own'",
            'products_variants' => "MODIFY `source` VARCHAR(16) NOT NULL DEFAULT 'OWN'",
            $customizations => "MODIFY `source` VARCHAR(16) NOT NULL DEFAULT 'own'",
            'normalized_products' => 'MODIFY `source` VARCHAR(32) NOT NULL',
            'categories_import_aliases' => 'MODIFY `source` VARCHAR(32) NOT NULL',
        ];
        foreach ($statements as $table => $statement) {
            if (Schema::hasTable($table) && str_starts_with($this->columnType($table, 'source'), 'enum')) {
                DB::statement("ALTER TABLE `{$table}` {$statement}");
            }
        }
        // Products that are not imported have no normalized row.
        if (Schema::hasTable($customizations) && $this->isNotNullable($customizations, 'normalized_product_id')) {
            DB::statement("ALTER TABLE `{$customizations}` MODIFY `normalized_product_id` BIGINT UNSIGNED NULL, MODIFY `normalized_variant_id` BIGINT UNSIGNED NULL");
        }
    }

    private function fixes(): void
    {
        $customizations = Schema::hasTable('customizations') ? 'customizations' : 'printing_variants';
        if (Schema::hasTable($customizations) && $this->indexColumns($customizations, 'variant_id_index') === ['id']) {
            Schema::table($customizations, fn (Blueprint $table) => $table->dropIndex('variant_id_index'));
        }
        if (Schema::hasTable($customizations) && $this->indexColumns($customizations, 'variant_id_index') === []) {
            Schema::table($customizations, fn (Blueprint $table) => $table->index('variant_id', 'variant_id_index'));
        }
        if (! Schema::hasColumn('order_items', 'unit_price')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->decimal('unit_price', 8, 2)->nullable()->after('price'));
        }
        // The inherited product_markups (starting_from_value, markup_percent) was read by nobody; the name is reused by part 2.
        if (Schema::hasTable('product_markups') && Schema::hasColumn('product_markups', 'starting_from_value')) {
            Schema::drop('product_markups');
        }
    }

    private function columnType(string $table, string $column): string
    {
        $row = DB::selectOne('SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);

        return $row ? strtolower((string) $row->t) : '';
    }

    private function isNotNullable(string $table, string $column): bool
    {
        $row = DB::selectOne('SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);

        return $row !== null && $row->n === 'NO';
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select('SELECT COLUMN_NAME AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX', [$table, $index]);

        return array_map(fn ($row) => (string) $row->c, $rows);
    }
};
