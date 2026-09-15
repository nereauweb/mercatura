<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Storefront product search. The engine (Algolia, Meilisearch, database,
 * collection…) is selected by config('mercatura.providers.search'); the
 * storefront never talks to a search SDK directly.
 */
interface SearchEngine
{
    /** Driver name, e.g. "algolia" or "collection". */
    public function key(): string;

    /**
     * Products matching the terms, most relevant first (header suggestions).
     *
     * @return Collection<int, Product>
     */
    public function suggestProducts(string $terms, int $limit = 10): Collection;

    /**
     * Product ids matching the terms, most relevant first (listing pages).
     *
     * @return list<int>
     */
    public function productIds(string $terms, int $limit = 100): array;
}
