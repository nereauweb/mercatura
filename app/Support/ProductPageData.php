<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Everything the product page renders, computed once per request so that
 * the Blade templates and the Alpine configurator read plain arrays.
 */
final class ProductPageData
{
    /**
     * @return array{
     *   breadcrumbs: list<array{name: string, url: string}>,
     *   gallery: list<array{full: string, thumb: string}>,
     *   colors: list<array{variant_id: int, sku: string, label: string, code: string, url: string, current: bool}>,
     *   configurator: array{colors: list<array<string, mixed>>, positions: list<array<string, mixed>>, has_printing: bool, has_packaging: bool, min_quantity: int},
     *   price_table: array{mode: string, columns: list<string>, rows: list<array{label: string, cells: list<string>}>, printed_columns: list<string>, printed_rows: list<array{label: string, cells: list<string>}>, printed_note: string|null, minCustomizationQuantity: int},
     *   details: list<array{label: string, value: string}>,
     *   packaging: list<array{label: string, value: string}>,
     *   related: Collection<int, Product>
     * }
     */
    public static function for(Product $product, ProductVariant $article, bool $hasPrinting, bool $hasPackaging): array
    {
        return [
            'breadcrumbs' => self::breadcrumbs($product, $article),
            'gallery' => self::gallery($article),
            'colors' => self::colors($product, $article),
            'configurator' => self::configurator($product, $article, $hasPrinting, $hasPackaging),
            'price_table' => self::priceTable($product, $article),
            'details' => self::details($product, $article, $hasPrinting),
            'packaging' => self::packaging($article),
            'related' => self::related($product),
        ];
    }

    /** @return list<array{name: string, url: string}> */
    private static function breadcrumbs(Product $product, ProductVariant $article): array
    {
        $items = [['name' => (string) config('brand.name'), 'url' => CanonicalUrl::absolute('/')]];
        $categories = $product->breadcrumb_categories();
        if ($categories) {
            if (isset($categories[1])) {
                $items[] = ['name' => $categories[1]->name, 'url' => $categories[1]->canonical_url()];
            }
            $items[] = ['name' => $categories[0]->name, 'url' => $categories[0]->canonical_url()];
        }
        $items[] = ['name' => (string) $product->name, 'url' => $product->canonical_url()];
        $items[] = ['name' => $article->sku.($article->color ? ' ('.$article->color->label.')' : ''), 'url' => route('frontend.product.show.by_slug.variant', ['slug' => $product->slug(), 'sku' => $article->sku])];

        return $items;
    }

    /** @return list<array{full: string, thumb: string}> */
    private static function gallery(ProductVariant $article): array
    {
        return $article->getMedia('image')->map(fn ($media) => [
            'full' => $media->getUrl(),
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            'alt' => (string) ($media->getCustomProperty('alt') ?? ''),
        ])->values()->all();
    }

    /** @return list<array{variant_id: int, sku: string, label: string, code: string, url: string, current: bool}> */
    private static function colors(Product $product, ProductVariant $article): array
    {
        return $product->color_variants()->with('color')->get()->filter(fn (ProductVariant $variant) => $variant->color !== null)->map(fn (ProductVariant $variant) => [
            'variant_id' => (int) $variant->id,
            'sku' => (string) $variant->sku,
            'label' => (string) $variant->color->label,
            'code' => (string) $variant->color->render_code(),
            'url' => route('frontend.product.show.by_slug.variant', ['slug' => $product->slug(), 'sku' => $variant->sku]),
            'current' => $variant->color_id === $article->color_id,
        ])->values()->all();
    }

    /** @return array{colors: list<array<string, mixed>>, positions: list<array<string, mixed>>, has_printing: bool, has_packaging: bool, min_quantity: int} */
    private static function configurator(Product $product, ProductVariant $article, bool $hasPrinting, bool $hasPackaging): array
    {
        $variants = $product->variants()->where('active', 1)->with(['color', 'size'])->get();
        $minQuantity = (int) $article->min_quantity();

        $colors = [];
        foreach ($variants as $variant) {
            if ($variant->color === null) {
                continue;
            }
            $colorId = (int) $variant->color_id;
            $colors[$colorId] ??= [
                'color_id' => $colorId,
                'label' => (string) $variant->color->label,
                'code' => (string) $variant->color->render_code(),
                'sizes' => [],
            ];
            $colors[$colorId]['sizes'][] = [
                'variant_id' => (int) $variant->id,
                'label' => $variant->size ? (string) $variant->size->shown_label() : __('frontend.product.configurator.one_size'),
                'stock' => (int) $variant->stock,
                'next_stock_quantity' => (int) $variant->next_stock_quantity,
                'next_stock_date' => $variant->next_stock_date ? (string) $variant->next_stock_date : null,
                'min_quantity' => $minQuantity,
            ];
        }
        $colors = array_values($colors);

        $positions = [];
        if ($hasPrinting) {
            $printings = $article->customizations()->get();
            foreach ($printings->groupBy('position_label')->sortKeys() as $label => $group) {
                $positions[] = [
                    'id' => (int) $group->first()->id,
                    'label' => ucfirst((string) $label),
                    'image' => $group->first()->image ?: null,
                    'techniques' => $group->sortBy('technique_label')->map(fn ($printing) => ['id' => (int) $printing->id, 'label' => ucfirst((string) $printing->technique_label)])->values()->all(),
                ];
            }
        }

        return [
            'colors' => $colors,
            'positions' => $positions,
            'has_printing' => $hasPrinting,
            'has_packaging' => $hasPackaging,
            'min_quantity' => (int) ($article->ordered_prices()->first()->from_quantity ?? 0),
        ];
    }

    /** @return array{mode: string, columns: list<string>, rows: list<array{label: string, cells: list<string>}>, printed_columns: list<string>, printed_rows: list<array{label: string, cells: list<string>}>, printed_note: string|null, minCustomizationQuantity: int} */
    private static function priceTable(Product $product, ProductVariant $article): array
    {
        $format = fn ($value) => number_format((float) $value, 2, ',', '').'&nbsp;€';
        $perSizeCategories = (array) config('mercatura.catalog.per_size_price_table_categories', []);
        $perSize = $perSizeCategories !== [] && $product->categories->pluck('id')->intersect($perSizeCategories)->isNotEmpty() && $product->size_variants_count() > 1;
        $defaultPrinting = $article->defaultCustomization();
        $minPrint = (int) $article->minCustomizationQuantity();
        $prices = $article->ordered_prices();
        $single = $article->prices->count() === 1;

        $neutralColumns = [];
        $neutralCells = [];
        $printedColumns = [];
        $printedCells = [];
        $lastFrom = 0;
        $count = 0;
        foreach ($prices as $price) {
            if ($price->from_quantity > 1 || $single) {
                if (! $perSize || $count < 4) {
                    $neutralColumns[] = $price->from_quantity.($perSize ? '' : '+');
                    $neutralCells[] = $format($price->price);
                    $count++;
                }
            }
        }
        $count = 0;
        foreach ($prices as $price) {
            if ($perSize && $count >= 4) {
                break;
            }
            if ($price->from_quantity > 1 && $price->from_quantity >= $minPrint && $price->from_quantity != $lastFrom) {
                $printedColumns[] = (string) $price->from_quantity;
                $printedCells[] = $format($article->price_per_quantity($price->from_quantity, false, false, true));
                $lastFrom = $price->from_quantity;
                $count++;
            } elseif ($price->from_quantity == 1 && $minPrint > 1 && $minPrint != $lastFrom) {
                $printedColumns[] = $minPrint.'+';
                $printedCells[] = $format($article->price_per_quantity($minPrint, false, false, true));
                $lastFrom = $minPrint;
                $count++;
            }
        }

        $rows = [['label' => __('frontend.product.neutral'), 'cells' => $neutralCells]];
        $printedRows = [['label' => __('frontend.product.printed'), 'cells' => $printedCells]];

        if ($perSize) {
            $rows = [];
            $printedRows = [];
            foreach ($product->size_variants as $sizeVariant) {
                if (! $sizeVariant->size) {
                    continue;
                }
                $cells = [];
                $count = 0;
                foreach ($sizeVariant->ordered_prices() as $price) {
                    if ($price->from_quantity > 1 && $count < 4) {
                        $cells[] = $format($price->price);
                        $count++;
                    }
                }
                $rows[] = ['label' => (string) $sizeVariant->size->shown_label(), 'cells' => $cells];

                $cells = [];
                $lastFrom = 0;
                $count = 0;
                $sizeMin = (int) $sizeVariant->minCustomizationQuantity();
                foreach ($sizeVariant->ordered_prices() as $price) {
                    if ($count >= 4) {
                        break;
                    }
                    if ($price->from_quantity > 1 && $price->from_quantity >= $sizeMin && $price->from_quantity != $lastFrom) {
                        $cells[] = $format($sizeVariant->price_per_quantity($price->from_quantity, false, false, true));
                        $lastFrom = $price->from_quantity;
                        $count++;
                    } elseif ($price->from_quantity == 1 && $sizeMin > 1 && $sizeMin != $lastFrom) {
                        $cells[] = $format($sizeVariant->price_per_quantity($sizeMin, false, false, true));
                        $lastFrom = $sizeMin;
                        $count++;
                    }
                }
                $printedRows[] = ['label' => (string) $sizeVariant->size->shown_label(), 'cells' => $cells];
            }
        }

        return [
            'mode' => $perSize ? 'per_size' : 'simple',
            'columns' => $neutralColumns,
            'rows' => $rows,
            'printed_columns' => $printedColumns,
            'printed_rows' => $defaultPrinting ? $printedRows : [],
            'printed_note' => $defaultPrinting ? __('frontend.product.price_printed_note', ['technique' => $defaultPrinting->technique_label, 'position' => $defaultPrinting->position_label]) : null,
            'minCustomizationQuantity' => $minPrint,
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private static function details(Product $product, ProductVariant $article, bool $hasPrinting): array
    {
        $attributes = (array) config('mercatura.catalog.attributes', []);
        $rows = [];

        if ($article->size && $article->size->label !== 'N/A') {
            $sizes = $product->size_variants->filter(fn (ProductVariant $variant) => $variant->size !== null)->map(fn (ProductVariant $variant) => $variant->size->label)->implode(' ');
            $rows[] = ['label' => __('frontend.product.sizes'), 'value' => $sizes];
        }
        foreach (['material' => 'material', 'brand' => 'brand'] as $key => $labelKey) {
            $value = isset($attributes[$key]) ? $article->attribute_value($attributes[$key]) : false;
            if ($value) {
                $rows[] = ['label' => __('frontend.product.'.$labelKey), 'value' => (string) $value];
            }
        }
        if ($article->dimensions() != '') {
            $rows[] = ['label' => __('frontend.product.dimensions'), 'value' => (string) $article->dimensions()];
        }
        if ($article->min_quantity() > 1) {
            $rows[] = ['label' => __('frontend.product.min_order'), 'value' => __('frontend.product.pieces', ['count' => $article->min_quantity()])];
        }
        if ($hasPrinting && $article->maxCustomizationMinimumQuantity() > 1) {
            $rows[] = ['label' => __('frontend.product.min_print'), 'value' => __('frontend.product.pieces', ['count' => $article->minCustomizationQuantity()])];
        }

        return $rows;
    }

    /** @return list<array{label: string, value: string}> */
    private static function packaging(ProductVariant $article): array
    {
        $attributes = (array) config('mercatura.catalog.attributes', []);
        $rows = [];
        foreach (['pack_length' => ' cm', 'pack_width' => ' cm', 'pack_height' => ' cm', 'pack_pieces' => '', 'pack_weight' => ' kg'] as $key => $unit) {
            $value = isset($attributes[$key]) ? $article->attribute_value($attributes[$key]) : false;
            if ($value !== false && $value !== null && $value !== '') {
                $rows[] = ['label' => __('frontend.product.'.$key), 'value' => $value.$unit];
            }
        }

        return $rows;
    }

    /** @return Collection<int, Product> */
    private static function related(Product $product): Collection
    {
        /** @var Category|null $category */
        $category = $product->categories()->whereNotNull('parent_id')->first();
        $key = 'product_related_'.($category ? 'cat_'.$category->id : 'all');

        return Cache::remember($key, now()->addDay(), function () use ($category) {
            return Product::where('active', 1)->where('isBestseller', 1)
                ->when($category, fn ($q) => $q->whereHas('categories', fn ($sq) => $sq->where('categories.id', $category->id)))
                ->with(['color_variants.color', 'main_variant_relationship'])
                ->inRandomOrder()->limit(16)->get();
        });
    }
}
