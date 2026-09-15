<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Brands become a table (docs/02_V2B_ADMIN.md §3.7). products.brand stays
 * as the denormalised label the storefront reads; products.brand_id links
 * the row. Backfilled from the distinct labels, except empty, "0" and
 * "Unbranded" which mean "no brand".
 */
return new class extends Migration
{
    public function up(): void
    {
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

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('brand_id')->nullable()->after('brand')->constrained('brands')->nullOnDelete();
        });

        $labels = DB::table('products')->whereNotNull('brand')->whereNotIn('brand', ['', '0', 'Unbranded'])
            ->select('brand', DB::raw('MAX(brand_image) as logo'))->groupBy('brand')->orderBy('brand')->get();
        $position = 0;
        $slugs = [];
        foreach ($labels as $row) {
            // Labels differing only by symbols or case ("RFX", "RFX™") would share a slug.
            $base = Str::slug($row->brand) ?: 'brand-'.substr(md5($row->brand), 0, 8);
            $slug = $base;
            for ($n = 2; isset($slugs[$slug]); $n++) {
                $slug = $base.'-'.$n;
            }
            $slugs[$slug] = true;
            $id = DB::table('brands')->insertGetId([
                'name' => $row->brand,
                'slug' => $slug,
                'logo' => $row->logo ? '/img/brands/'.$row->logo : null,
                'position' => $position++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('products')->where('brand', $row->brand)->update(['brand_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('brand_id');
        });
        Schema::dropIfExists('brands');
    }
};
