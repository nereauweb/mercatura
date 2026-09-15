<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CatalogCache;
use InvalidArgumentException;

/**
 * Legacy AdminProductsController::setMainVariant: the variant must belong to
 * the product; Product::set_main_variant recomputes min/max price and cover.
 */
final class SetMainVariant
{
    public function handle(Product $product, int $variantId): ProductVariant
    {
        $variant = $product->variants()->whereKey($variantId)->first();
        if ($variant === null) {
            throw new InvalidArgumentException("Variant [{$variantId}] does not belong to product [{$product->id}].");
        }

        $product->set_main_variant($variant->id, true);
        CatalogCache::flush();

        return $variant;
    }

    /** After a variant is deleted or deactivated the product re-elects its main variant (legacy destroy/update). */
    public function reelect(Product $product): ?ProductVariant
    {
        $main = $product->main_variant(true);
        CatalogCache::flush();

        return $main;
    }
}
