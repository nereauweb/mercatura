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
        /** @var Collection<int, Product> $products */
        $products = Product::search($terms)->take($limit)->get();

        return $products->values();
    }

    public function productIds(string $terms, int $limit = 100): array
    {
        /** @var list<int> $ids */
        $ids = Product::search($terms)->take($limit)->keys()->map(fn ($id) => (int) $id)->values()->all();

        return $ids;
    }
}
