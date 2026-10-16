<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Livewire\FrontendProductList;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageContent;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Support\CatalogFilterOptions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** Listing filters read what the card shows: active variants only, exact brand. */
final class CatalogFiltersTest extends TestCase
{
    use DatabaseTransactions;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->category = Category::query()->create(['name' => 'Filtri test', 'slug' => 'filtri-test', 'active' => 1]);
    }

    private function product(string $sku, string $brand = 'Filtri Test'): Product
    {
        $product = Product::query()->create(['sku' => $sku, 'source' => 'own', 'source_sku' => $sku, 'name' => 'Prodotto '.$sku, 'slug' => 'prodotto-'.strtolower($sku), 'brand' => $brand, 'active' => 1, 'forced_status' => 'none']);
        $product->categories()->attach($this->category->id);

        return $product;
    }

    private function variant(Product $product, string $sku, int $colorId, bool $active = true, int $isSale = 0): ProductVariant
    {
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => $sku, 'active' => $active ? 1 : 0, 'isSale' => $isSale, 'color_id' => $colorId,
            'size_id' => (int) config('mercatura.catalog.one_size_id', 52), 'stock' => 10, 'next_stock_quantity' => 0, 'source' => 'OWN', 'source_sku' => $sku,
        ]);
        if (! $product->main_variant_id) {
            $product->forceFill(['main_variant_id' => $variant->id])->save();
        }

        return $variant;
    }

    /** @return list<string> */
    private function listed(array $set = [], array $mount = []): array
    {
        $test = Livewire::test(FrontendProductList::class, $mount + ['category' => $this->category->id, 'brand' => false]);
        foreach ($set as $property => $value) {
            $test->set($property, $value);
        }

        return collect($test->viewData('products')->items())->pluck('sku')->sort()->values()->all();
    }

    public function test_a_colour_offered_only_by_inactive_variants_is_neither_a_facet_nor_a_match(): void
    {
        $blue = ProductColor::query()->create(['label' => 'Blu filtri', 'code' => '#0000ff']);
        $red = ProductColor::query()->create(['label' => 'Rosso filtri', 'code' => '#ff0000']);
        $product = $this->product('FLT-COL');
        $this->variant($product, 'FLT-COL-B', $blue->id);
        $this->variant($product, 'FLT-COL-R', $red->id, active: false);

        $facet = array_column(CatalogFilterOptions::forContext($this->category->id, null)['colors'], 'id');
        $this->assertContains($blue->id, $facet);
        $this->assertNotContains($red->id, $facet, 'the colour of a disabled variant is not offered');

        $this->assertSame(['FLT-COL'], $this->listed(['colors' => [(string) $blue->id]]));
        $this->assertSame([], $this->listed(['colors' => [(string) $red->id]]), 'nor matched');
    }

    public function test_on_sale_means_an_active_variant_on_sale_for_badge_listing_and_cms_page(): void
    {
        $color = ProductColor::query()->create(['label' => 'Nero filtri', 'code' => '#000000']);
        $imported = $this->product('FLT-SALE1');
        $this->variant($imported, 'FLT-SALE1-A', $color->id, isSale: 1);
        $forced = $this->product('FLT-SALE2');
        $this->variant($forced, 'FLT-SALE2-A', $color->id, isSale: 2);
        $disabled = $this->product('FLT-SALE0');
        $this->variant($disabled, 'FLT-SALE0-A', $color->id);
        $this->variant($disabled, 'FLT-SALE0-B', $color->id, active: false, isSale: 1);

        $this->assertSame(['FLT-SALE1', 'FLT-SALE2'], $this->listed(['is_sale' => true]));
        $this->assertTrue($imported->isSale());
        $this->assertTrue($forced->isSale(), 'a sale forced in the panel shows the badge too');
        $this->assertFalse($disabled->isSale(), 'a disabled variant does not put the product on sale');

        $page = Page::query()->create(['title' => 'Offerte', 'slug' => 'offerte-filtri-test', 'active' => 1, 'navbar' => 0, 'products' => 1, 'text' => '<p>x</p>']);
        PageContent::query()->create(['page_id' => $page->id, 'filter_type' => 'is_sale', 'filter_target' => null, 'filter_value' => '1']);
        $ids = $page->products_ids()->all();
        $this->assertContains($imported->id, $ids);
        $this->assertContains($forced->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
    }

    public function test_the_price_filter_reads_the_starting_price_of_the_card_bounds_included(): void
    {
        $color = ProductColor::query()->create(['label' => 'Verde filtri', 'code' => '#00ff00']);
        foreach (['FLT-P1' => [1.50, 2.40], 'FLT-P4' => [4.00, 7.00], 'FLT-P5' => [5.00, 5.80], 'FLT-P9' => [9.00, 12.00]] as $sku => [$from, $upTo]) {
            $product = $this->product($sku);
            $this->variant($product, $sku.'-A', $color->id);
            $product->forceFill(['variants_min_price' => $from, 'variants_max_price' => $upTo])->save();
        }

        $this->assertSame(['FLT-P1', 'FLT-P4', 'FLT-P5'], $this->listed(['priceMaxInput' => '5']), '"da € 4,00" is under 5 even if one piece costs 7');
        $this->assertSame(['FLT-P4', 'FLT-P5'], $this->listed(['priceMinInput' => '4', 'priceMaxInput' => '5']), 'bounds included');
        $this->assertSame(['FLT-P9'], $this->listed(['priceMinInput' => '6']));
        $this->assertSame([1.5, 9.0], CatalogFilterOptions::forContext($this->category->id, null)['price'], 'the range offered is the range of starting prices');
    }

    public function test_a_brand_page_lists_that_brand_only(): void
    {
        $color = ProductColor::query()->create(['label' => 'Bianco filtri', 'code' => '#ffffff']);
        $this->variant($this->product('FLT-BR1', 'Acme Filtri'), 'FLT-BR1-A', $color->id);
        $this->variant($this->product('FLT-BR2', 'Acme Filtri WRK'), 'FLT-BR2-A', $color->id);

        $this->assertSame(['FLT-BR1'], $this->listed(mount: ['category' => false, 'brand' => 'Acme Filtri']));
        $html = $this->get('/prodotti/marchi/'.rawurlencode('Acme Filtri'))->assertOk()->getContent();
        $this->assertStringContainsString('Prodotto FLT-BR1', $html);
        $this->assertStringNotContainsString('Prodotto FLT-BR2', $html);
    }
}
