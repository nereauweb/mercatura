<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogCache;

/**
 * Legacy rule (AdminProductsController::store, bulk change_category_action):
 * selecting a child category always attaches its parent too.
 */
final class SyncProductCategories
{
    /**
     * @param  list<int>  $categoryIds
     */
    public function handle(Product $product, array $categoryIds, bool $replace = true): void
    {
        $ids = self::withParents($categoryIds);
        if ($replace) {
            $product->categories()->sync($ids);
        } else {
            $product->categories()->syncWithoutDetaching($ids);
        }
        CatalogCache::flush();
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public static function withParents(array $categoryIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $categoryIds)));
        $parents = Category::query()->whereIn('id', $ids)->whereNotNull('parent_id')->pluck('parent_id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_unique([...$ids, ...$parents]));
    }
}
