<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Support\CatalogCache;
use Illuminate\Support\Facades\DB;

/**
 * Legacy AdminProductCategoriesController::destroy: children go with the
 * parent, products are detached (never deleted), import aliases keep their
 * row with category_id set to null.
 */
final class DeleteCategory
{
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            foreach ($category->children()->get() as $child) {
                $child->products()->detach();
                $child->aliases()->update(['category_id' => null]);
                $child->delete();
            }
            $category->products()->detach();
            $category->aliases()->update(['category_id' => null]);
            $category->delete();
        });
        CatalogCache::flush();
    }
}
