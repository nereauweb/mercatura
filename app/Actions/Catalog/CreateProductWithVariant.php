<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Support\CatalogCache;
use Illuminate\Support\Facades\DB;

/**
 * Legacy AdminProductsController::store without its magic defaults: a
 * product is created with one variant (colour and size chosen, one price
 * tier from quantity 1, optional cover) which becomes the main variant.
 */
final class CreateProductWithVariant
{
    /**
     * @param  array<string, mixed>  $product  product attributes (sku, name, description, brand_id, flags, seo…)
     * @param  list<int>  $categoryIds
     * @param  array{color_id: int, size_id: int|null, stock?: int|null, price?: float|int|string|null, sku?: string|null, image_path?: string|null}  $variant
     */
    public function handle(array $product, array $categoryIds, array $variant): Product
    {
        return DB::transaction(function () use ($product, $categoryIds, $variant): Product {
            $product['sku'] = trim((string) ($product['sku'] ?? ''));
            $product['source'] ??= 'own';
            $product['source_sku'] ??= $product['sku'];
            $product['active'] ??= 1;
            $record = Product::query()->create($product);
            if ($product['sku'] === '') {
                $record->sku = (string) $record->id;
                $record->source_sku = $record->sku;
                $record->save();
            }
            $record->refresh();
            $record->slug(true);

            $main = ProductVariant::query()->create([
                'product_id' => $record->id,
                'sku' => trim((string) ($variant['sku'] ?? '')) !== '' ? trim((string) $variant['sku']) : $record->sku.'.1',
                'source_sku' => trim((string) ($variant['sku'] ?? '')) !== '' ? trim((string) $variant['sku']) : $record->sku,
                'active' => (int) $record->active,
                'color_id' => (int) $variant['color_id'],
                'size_id' => $variant['size_id'] ?? null,
                'stock' => (int) ($variant['stock'] ?? 0),
                'source' => strtoupper((string) $record->source),
            ]);
            if (isset($variant['price']) && is_numeric($variant['price'])) {
                ProductVariantPrice::query()->create([
                    'variant_id' => $main->id, 'from_quantity' => 1,
                    'price' => (float) $variant['price'], 'original_price' => (float) $variant['price'], 'included_additional_costs' => 0,
                ]);
            }
            if (! empty($variant['image_path']) && is_file((string) $variant['image_path'])) {
                $main->addMedia((string) $variant['image_path'])->toMediaCollection('image');
            }

            app(SyncProductCategories::class)->handle($record, $categoryIds);
            $record->set_main_variant($main->id, true);
            CatalogCache::flush();

            return $record->refresh();
        });
    }
}
