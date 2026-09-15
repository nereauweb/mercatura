<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\ImportData\VariantPrinting;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariantPrice;
use App\Support\Connectors\PrintingPipeline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Filter options and sidebar navigation for a catalogue context (category,
 * brand or the whole catalogue). Cached because they are expensive and
 * change only with imports; kept out of the Livewire snapshot so that the
 * listing page stays small.
 */
final class CatalogFilterOptions
{
    private const TTL_SECONDS = 3600;

    /**
     * @return array{colors: list<array{id: int, label: string, code: string}>, brands: list<array{name: string, count: int}>, prints: list<array{label: string, count: int}>, price: array{0: float, 1: float}}
     */
    public static function forContext(?int $categoryId, ?string $brand): array
    {
        $key = 'catalog_filters_'.($categoryId ? 'cat_'.$categoryId : ($brand ? 'brand_'.md5($brand) : 'all'));

        return Cache::remember($key, self::TTL_SECONDS, function () use ($categoryId, $brand): array {
            $productIds = self::productIds($categoryId, $brand);
            $category = $categoryId ? Category::find($categoryId) : null;

            $brands = $brand ? [] : DB::table('products')->select('brand', DB::raw('count(id) as count'))
                ->whereIn('id', $productIds)->groupBy('brand')->get()
                ->filter(fn ($row) => $row->brand && $row->brand !== 'Unbranded')
                ->map(fn ($row) => ['name' => (string) $row->brand, 'count' => (int) $row->count])->values()->all();

            $colors = ProductColor::whereHas('variants', fn ($q) => $q->whereIn('products_variants.product_id', $productIds))->get()
                ->map(fn (ProductColor $color) => ['id' => (int) $color->id, 'label' => (string) $color->label, 'code' => (string) $color->render_code()])->values()->all();

            $prints = VariantPrinting::whereIn('product_id', $productIds)
                ->tap(fn ($q) => PrintingPipeline::apply($q))
                ->select('technique_label', 'position_label', DB::raw('COUNT(DISTINCT product_id) as count'))
                ->groupBy('technique_label', 'position_label')
                ->get()
                ->groupBy('technique_label')
                ->map(fn ($group, $label) => ['label' => (string) $label, 'count' => (int) $group->sum('count')])
                ->values()->all();

            $price = [0.0, 100.0];
            if ($category) {
                $range = $category->get_products_min_max_prices();
                $price = [(float) $range[0], (float) $range[1]];
            } else {
                $min = ProductVariantPrice::query()->min('price');
                $max = ProductVariantPrice::query()->max('price');
                if ($min !== null && $max !== null) {
                    $price = [(float) $min, (float) $max];
                }
            }

            return ['colors' => $colors, 'brands' => $brands, 'prints' => $prints, 'price' => $price];
        });
    }

    /**
     * Sidebar navigation: children of a root category, siblings of a child, or the roots.
     *
     * @return list<array{id: int, name: string, slug: string, count: int}>
     */
    public static function navigationFor(?int $categoryId): array
    {
        return Cache::remember('catalog_navigation_'.($categoryId ?: 'root'), self::TTL_SECONDS, function () use ($categoryId): array {
            $category = $categoryId ? Category::find($categoryId) : null;

            $query = match (true) {
                $category === null => Category::whereNull('parent_id'),
                $category->parent_id !== null => $category->siblings(),
                default => $category->children(),
            };

            return $query->where('active', 1)->withCount('products')->orderBy('position')->get()
                ->map(fn (Category $item) => ['id' => (int) $item->id, 'name' => (string) $item->name, 'slug' => (string) $item->slug(), 'count' => (int) $item->products_count])
                ->values()->all();
        });
    }

    /** @return list<int> */
    private static function productIds(?int $categoryId, ?string $brand): array
    {
        if ($categoryId) {
            return Product::where('active', 1)->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))->pluck('products.id')->all();
        }
        if ($brand) {
            return Product::where('active', 1)->where('brand', 'LIKE', '%'.$brand.'%')->pluck('products.id')->all();
        }

        return Product::where('active', 1)->pluck('products.id')->all();
    }
}
