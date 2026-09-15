<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Storefront caches derived from the catalogue (navigation, filter options,
 * bestsellers, related products, page listings) are keyed per category,
 * brand and product and have no tags on the file driver: after an admin
 * write the whole application cache is flushed. Installations sharing a
 * cache server with other applications must give Mercatura its own
 * database/prefix (deploy/README.md).
 */
final class CatalogCache
{
    public static function flush(): void
    {
        Cache::flush();
    }
}
