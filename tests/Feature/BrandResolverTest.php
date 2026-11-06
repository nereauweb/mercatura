<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Support\BrandResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Import labels → brand records (BrandResolver, used by app:ProcessNormalizedProductData). */
final class BrandResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unknown_labels_create_one_active_brand(): void
    {
        $resolver = new BrandResolver;

        $brand = $resolver->resolve('Acme Gadgets®');

        $this->assertNotNull($brand);
        $this->assertSame('acme-gadgets', $brand->slug);
        $this->assertTrue($brand->active);
        $this->assertSame($brand->id, $resolver->resolve('Acme Gadgets®')?->id);
        $this->assertSame($brand->id, (new BrandResolver)->resolve('Acme Gadgets®')?->id, 'a later run finds it');
        $this->assertSame(1, Brand::query()->where('slug', 'acme-gadgets')->count());
    }

    public function test_no_brand_labels_resolve_to_null(): void
    {
        $resolver = new BrandResolver;
        $before = Brand::withTrashed()->count();

        foreach (['', '0', 'Unbranded', false, null] as $label) {
            $this->assertNull($resolver->resolve($label));
        }
        $this->assertSame($before, Brand::withTrashed()->count());
    }

    public function test_a_brand_renamed_in_the_panel_still_matches_by_slug(): void
    {
        $brand = Brand::query()->create(['name' => 'Acme (rinominato)', 'slug' => 'acme-test', 'active' => true]);

        $this->assertSame($brand->id, (new BrandResolver)->resolve('Acme Test')?->id);
    }

    public function test_a_brand_deleted_in_the_panel_is_not_recreated(): void
    {
        $brand = Brand::query()->create(['name' => 'Cancellato', 'slug' => 'cancellato', 'active' => true]);
        $brand->delete();

        $this->assertNull((new BrandResolver)->resolve('Cancellato'));
        $this->assertSame(1, Brand::withTrashed()->where('slug', 'cancellato')->count());
    }
}
