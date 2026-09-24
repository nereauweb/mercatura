<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Support\HomeSlideImages;
use Database\Seeders\Demo\Images;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/** Home slideshow WebP sidecars: web 800, large 1600, original JPEG kept. */
final class HomeSlideImagesTest extends TestCase
{
    public function test_convert_writes_web_and_large_webp_and_banner_srcset(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('home_slides/hero.jpg', Images::banner(1200, 420, '1E3A8A', '60A5FA'));

        HomeSlideImages::convert('hero.jpg');

        Storage::disk('public')->assertExists('home_slides/conversions/hero-web.webp');
        Storage::disk('public')->assertExists('home_slides/conversions/hero-large.webp');

        $banner = HomeSlideImages::banner('hero.jpg');
        $this->assertSame('/storage/home_slides/conversions/hero-web.webp', $banner['src']);
        $this->assertSame('/storage/home_slides/conversions/hero-web.webp 800w, /storage/home_slides/conversions/hero-large.webp 1600w', $banner['srcset']);
        $this->assertTrue($banner['webp']);
    }

    public function test_banner_falls_back_to_the_original_when_conversions_are_missing(): void
    {
        Storage::fake('public');
        $banner = HomeSlideImages::banner('missing.jpg');
        $this->assertSame('/storage/home_slides/missing.jpg', $banner['src']);
        $this->assertSame('', $banner['srcset']);
        $this->assertFalse($banner['webp']);
    }

    public function test_slide_image_emits_srcset_for_web_and_large(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('home_slides/hero.jpg', Images::banner(1200, 420, '1E3A8A', '60A5FA'));
        HomeSlideImages::convert('hero.jpg');

        $slide = new \App\Models\ContentHomeSlide;
        $slide->background_image = 'hero.jpg';
        $slide->mobile_image = null;
        $slide->title_text = 'Catalogo';
        $slide->subtitle_text = '';

        $html = view('frontend.components.home.slide-image', [
            'slide' => $slide,
            'priority' => true,
            'mobileMax' => 639,
            'width' => 1600,
            'height' => 420,
            'alt' => 'Catalogo',
            'imgClass' => 'h-full w-full object-cover',
            'attributes' => new ComponentAttributeBag,
        ])->render();

        $this->assertStringContainsString('src="/storage/home_slides/conversions/hero-web.webp"', $html);
        $this->assertStringContainsString('srcset="/storage/home_slides/conversions/hero-web.webp 800w, /storage/home_slides/conversions/hero-large.webp 1600w"', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }
}
