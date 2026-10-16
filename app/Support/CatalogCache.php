<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\WarmCatalogCache;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Storefront caches derived from the catalogue (navigation, filter options,
 * bestsellers, related products, page listings) are keyed per category,
 * brand and product and have no tags on the file driver: after an admin
 * write or an import stage the whole application cache is flushed.
 * Installations sharing a cache server with other applications must give
 * Mercatura its own database/prefix (deploy/README.md).
 *
 * A flush queues a rebuild of what a cold visitor waits for (warm()),
 * WARM_DELAY seconds later; each flush starts a new generation and only
 * the job of the latest one runs, so a burst of panel saves warms once.
 */
final class CatalogCache
{
    private const GENERATION_KEY = 'catalog_cache_generation';

    private const WARM_DELAY = 60;

    public static function flush(): void
    {
        Cache::flush();
        $generation = (string) Str::uuid();
        Cache::forever(self::GENERATION_KEY, $generation);
        // Without a queue worker (sync: tests, local) the rebuild would run inside the request that flushed.
        if (config('queue.default') !== 'sync') {
            WarmCatalogCache::dispatch($generation)->delay(now()->addSeconds(self::WARM_DELAY));
        }
    }

    public static function isCurrent(string $generation): bool
    {
        return Cache::get(self::GENERATION_KEY) === $generation;
    }

    /**
     * Filter options and sidebar navigation of the whole catalogue and of
     * every active category: the part of a listing that costs seconds when
     * cold. Product pages are not warmed (thousands, ~0.25 s cold).
     *
     * @return int the contexts warmed
     */
    public static function warm(): int
    {
        CatalogFilterOptions::forContext(null, null);
        CatalogFilterOptions::navigationFor(null);
        $contexts = 1;
        foreach (Category::query()->where('active', 1)->orderBy('id')->pluck('id') as $id) {
            CatalogFilterOptions::forContext((int) $id, null);
            CatalogFilterOptions::navigationFor((int) $id);
            $contexts++;
        }

        return $contexts;
    }
}
