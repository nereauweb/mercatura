<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Livewire\FrontendProductList;
use Livewire\Livewire;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/** Phase 4(c): catalogue and search. */
final class CatalogPageTest extends TestCase
{
    /** @return list<string> */
    private function areaFiles(): array
    {
        return [
            resource_path('views/frontend/pages/list.blade.php'),
            resource_path('views/livewire/frontend-product-list.blade.php'),
            resource_path('views/frontend/components/pagination.blade.php'),
            resource_path('views/frontend/components/breadcrumb.blade.php'),
            resource_path('views/frontend/components/product/slider.blade.php'),
        ];
    }

    public function test_catalogue_templates_contain_no_uikit_jquery_or_client_identity(): void
    {
        foreach ($this->areaFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, basename($file).' still carries UIkit classes');
            $this->assertDoesNotMatchRegularExpression('/\$\(|jQuery|UIkit\.|select2|noUiSlider/', $source, basename($file).' still uses legacy scripts');
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern('fg-|bg-blu|bg-arancione'), $source, basename($file).' still carries client content');
        }
    }

    public function test_listing_page_renders_with_noindex_breadcrumb_and_filters(): void
    {
        $html = $this->get('/prodotti')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('wire:model.live="is_green"', $html);
        $this->assertStringContainsString('wire:model.live="urlSort"', $html);
        $this->assertStringContainsString(__('frontend.catalog.all_products'), $html);
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));
        $this->assertStringNotContainsString('uk-', $main);
    }

    public function test_unknown_category_slug_is_not_found(): void
    {
        // Since v2b.0 an unknown slug is a real 404 (so stored redirects can answer) instead of a soft-404 full listing.
        $this->get('/categorie/nessuna-categoria')->assertNotFound();
    }

    public function test_livewire_list_keeps_the_url_contract_and_applies_filters(): void
    {
        Livewire::test(FrontendProductList::class, ['category' => false, 'brand' => false])
            ->assertSee(__('frontend.catalog.filters'))
            ->set('is_green', true)
            ->assertSet('is_green', true)
            ->set('colors', ['1'])
            ->assertSet('urlColor', '1')
            ->set('brands', ['Acme'])
            ->assertSet('urlBrand', 'Acme')
            ->set('priceMinInput', '5')
            ->assertSet('min_price', 5.0)
            ->set('urlSort', 'name')
            ->set('limit', 24)
            ->call('resetFilters')
            ->assertSet('urlColor', '')
            ->assertSet('min_price', 0)
            ->assertSet('urlSort', 'name', 'sorting is not a filter');
    }

    public function test_legacy_filter_events_still_work(): void
    {
        Livewire::test(FrontendProductList::class, ['category' => false, 'brand' => false])
            ->dispatch('filter_colors', jsonColorsArray: json_encode(['3', '4']))
            ->assertSet('urlColor', '3,4')
            ->dispatch('filter_prices', jsonRequestedPricesArray: json_encode([2, 50]))
            ->assertSet('max_price', 50.0)
            ->dispatch('select_orderby', requestedOrderby: 'position')
            ->assertSet('urlSort', 'position')
            ->call('expand', 'colors')
            ->assertSet('expanded.colors', true);
    }
}
