<?php

declare(strict_types=1);

namespace App\Drivers\Search;

use App\Contracts\SearchEngine;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Laravel Scout on the Product model. The Scout engine (algolia, meilisearch,
 * typesense, database, collection, null) is config('mercatura.providers.search'),
 * mirrored into scout.driver by MercaturaServiceProvider; index settings stay
 * in config/scout.php.
 */
final class ScoutSearchEngine implements SearchEngine
{
    public function key(): string
    {
        return (string) config('scout.driver');
    }

    public function suggestProducts(string $terms, int $limit = 10): Collection
    {
        // The index may still hold a product switched off since its last sync: never suggest it.
        /** @var Collection<int, Product> $products */
        $products = Product::search($terms)->take($limit * 2)->get()->filter(fn (Product $product) => $product->active)->take($limit);

        return $products->values();
    }

    public function productIds(string $terms, int $limit = 100): array
    {
        /** @var list<int> $ids */
        $ids = Product::search($terms)->take($limit)->keys()->map(fn ($id) => (int) $id)->values()->all();
        $ids = Product::query()->whereIn('id', $ids)->where('active', 1)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $ids;
    }
}
