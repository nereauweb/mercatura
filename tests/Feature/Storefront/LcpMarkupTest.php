<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Livewire\FrontendProductList;
use Tests\TestCase;

/** One LCP image per page: hero/gallery/first card, never the logo; static Cache-Control. */
final class LcpMarkupTest extends TestCase
{
    public function test_card_is_lazy_unless_eager_or_priority(): void
    {
        $card = (string) file_get_contents(resource_path('views/frontend/components/product/card.blade.php'));
        $this->assertStringContainsString("@props(['product', 'eager' => false, 'priority' => false])", $card);
        $this->assertStringContainsString("loading=\"{{ (\$eager || \$priority) ? 'eager' : 'lazy' }}\"", $card);
        $this->assertStringContainsString('@if($priority) fetchpriority="high" @endif', $card);
    }

    public function test_header_logo_has_no_fetchpriority(): void
    {
        $header = (string) file_get_contents(resource_path('views/frontend/public/header.blade.php'));
        $this->assertStringNotContainsString('fetchpriority', $header);
    }

    public function test_home_preloads_the_hero_slide(): void
    {
        $page = (string) file_get_contents(resource_path('views/frontend/pages/home.blade.php'));
        $this->assertStringContainsString('x-frontend::home.hero-preload', $page);

        $preload = (string) file_get_contents(resource_path('views/frontend/components/home/hero-preload.blade.php'));
        $this->assertStringContainsString('rel="preload" as="image"', $preload);
        $this->assertStringContainsString('HomeSlideImages::banner', $preload);
        $this->assertStringContainsString('imagesrcset=', $preload);
    }

    public function test_product_page_preloads_the_gallery_lcp_image(): void
    {
        $page = (string) file_get_contents(resource_path('views/frontend/pages/product.blade.php'));
        $this->assertStringContainsString('rel="preload" as="image"', $page);
        $this->assertStringContainsString("\$page['gallery'][0]['web']", $page);
        $this->assertStringContainsString('imagesrcset=', $page);
    }

    public function test_htaccess_sets_long_cache_on_skins_and_storage(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('max-age=31536000, immutable', $htaccess);
        $this->assertStringContainsString('max-age=2592000', $htaccess);
        $this->assertStringContainsString('^/storage/', $htaccess);
    }

    public function test_logo_is_not_high_priority_on_home_or_listing(): void
    {
        $homeHeader = $this->headerHtml($this->get('/')->assertOk()->getContent());
        $this->assertStringNotContainsString('fetchpriority="high"', $homeHeader);

        $listingHeader = $this->headerHtml($this->get('/prodotti')->assertOk()->getContent());
        $this->assertStringNotContainsString('fetchpriority="high"', $listingHeader);
    }

    private function headerHtml(string $html): string
    {
        $this->assertSame(1, preg_match('/<header\b[\s\S]*?<\/header>/', $html, $match));

        return $match[0];
    }

    public function test_livewire_list_defaults_to_prioritizing_the_first_grid_card(): void
    {
        $this->assertTrue((new FrontendProductList)->prioritizeFirstCard);
    }
}
