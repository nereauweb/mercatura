<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Brand;
use App\Support\BrandLogo;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** Brand logo over the product card thumbnail (BrandLogo + frontend.components.product.card). */
final class BrandLogoTest extends TestCase
{
    use DatabaseTransactions;

    private string $legacyFile = 'zz-test-legacy-brand.png';

    protected function tearDown(): void
    {
        File::delete(public_path('img/brands/'.$this->legacyFile));
        parent::tearDown();
    }

    public function test_the_brand_record_logo_wins_and_is_bounded_on_the_card(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $brand = Brand::query()->create(['name' => 'Acme Test', 'slug' => 'acme-test', 'logo' => '/storage/brands/acme.png', 'active' => true]);
        $f->product->forceFill(['brand' => 'Acme Test', 'brand_id' => $brand->id, 'brand_image' => $this->legacyFile])->save();

        $this->assertSame(['url' => '/storage/brands/acme.png', 'alt' => 'Acme Test'], BrandLogo::for($f->product));

        $html = (string) $this->blade('<x-frontend::product.card :product="$product" />', ['product' => $f->product]);
        $this->assertStringContainsString('src="/storage/brands/acme.png"', $html);
        $this->assertStringContainsString('max-h-7', $html, 'tall marks are capped in height');
        $this->assertStringContainsString('max-w-16', $html, 'wide wordmarks are capped in width');
    }

    public function test_an_inactive_or_logo_less_brand_shows_nothing(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $this->writeLegacyFile();
        $brand = Brand::query()->create(['name' => 'Spento', 'slug' => 'spento', 'logo' => '/storage/brands/spento.png', 'active' => false]);
        $f->product->forceFill(['brand' => 'Spento', 'brand_id' => $brand->id, 'brand_image' => $this->legacyFile])->save();

        $this->assertNull(BrandLogo::for($f->product), 'the record decides, the legacy file is not a fallback for linked products');
    }

    public function test_unlinked_products_fall_back_to_the_legacy_file(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $f->product->forceFill(['brand' => 'Vecchio', 'brand_id' => null, 'brand_image' => $this->legacyFile])->save();

        $this->assertNull(BrandLogo::for($f->product), 'no file, no logo');

        $this->writeLegacyFile();
        $this->assertSame(['url' => '/img/brands/'.$this->legacyFile, 'alt' => 'Vecchio'], BrandLogo::for($f->product));
    }

    private function writeLegacyFile(): void
    {
        File::ensureDirectoryExists(public_path('img/brands'));
        File::put(public_path('img/brands/'.$this->legacyFile), 'png');
    }
}
