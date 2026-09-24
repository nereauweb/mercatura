<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\ProductVariant;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/** Named WebP conversions on variant images: thumb 320, web 800, large 1600. */
final class ProductImageConversionsTest extends TestCase
{
    public function test_variant_registers_named_webp_conversions(): void
    {
        $variant = new ProductVariant;
        $variant->registerMediaConversions();
        $byName = collect($variant->mediaConversions)->keyBy(fn ($conversion) => $conversion->getName());

        $this->assertSame(['thumb', 'web', 'large'], $byName->keys()->all());
        foreach ($byName as $conversion) {
            $this->assertSame('webp', $conversion->getResultExtension('jpg'));
        }
        $this->assertFalse($byName['thumb']->shouldBeQueued());
        $this->assertTrue($byName['web']->shouldBeQueued());
        $this->assertTrue($byName['large']->shouldBeQueued());
    }

    public function test_gallery_emits_srcset_for_web_and_large(): void
    {
        $html = view('frontend.components.product.gallery', [
            'images' => [[
                'full' => '/storage/1/cover.jpg',
                'thumb' => '/storage/1/conversions/cover-thumb.webp',
                'web' => '/storage/1/conversions/cover-web.webp',
                'large' => '/storage/1/conversions/cover-large.webp',
                'alt' => 'Bottiglia',
            ]],
            'alt' => 'Prodotto',
            'badges' => [],
            'attributes' => new ComponentAttributeBag,
        ])->render();

        $this->assertStringContainsString('src="/storage/1/conversions/cover-web.webp"', $html);
        $this->assertStringContainsString('srcset="/storage/1/conversions/cover-web.webp 800w, /storage/1/conversions/cover-large.webp 1600w"', $html);
        $this->assertStringContainsString('sizes="(max-width: 767px) 92vw, 28rem"', $html);
        $this->assertStringContainsString('cover-large.webp', $html);
        $core = (string) file_get_contents(resource_path('views/frontend/components/product/gallery.blade.php'));
        $this->assertStringContainsString("srcset=\"{{ \$image['web'] }} 800w, {{ \$image['large'] }} 1600w\"", $core);
    }

    public function test_unknown_variant_cover_is_not_found(): void
    {
        $this->get('/variante/999999999/cover')->assertNotFound();
    }
}
