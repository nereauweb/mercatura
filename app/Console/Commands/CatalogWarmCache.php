<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CatalogCache;
use Illuminate\Console\Command;

/** Rebuilds the listing caches now (deploy/deploy.sh runs it after the caches are cleared). */
final class CatalogWarmCache extends Command
{
    protected $signature = 'catalog:warm-cache';

    protected $description = 'Rebuild the storefront listing caches (filter options, navigation) of the catalogue and of every active category';

    public function handle(): int
    {
        $start = microtime(true);
        $contexts = CatalogCache::warm();
        $this->info($contexts.' contexts warmed in '.round(microtime(true) - $start, 1).' s');

        return self::SUCCESS;
    }
}
