<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Support\CatalogCache;

/**
 * Bulk "Segna come bestseller" / "Togli dai bestseller" of the products
 * list. The flag is editorial: no import writes it, the search index does
 * not carry it.
 */
final class SetBestseller
{
    /**
     * @param  list<int|string>  $productIds
     */
    public function handle(array $productIds, bool $bestseller): int
    {
        $count = Product::query()->whereIn('id', $productIds)->update(['isBestseller' => $bestseller ? 1 : 0]);
        CatalogCache::flush();

        return $count;
    }
}
