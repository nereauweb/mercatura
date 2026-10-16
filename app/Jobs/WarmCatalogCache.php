<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\CatalogCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Queued by CatalogCache::flush(); a newer flush makes it a no-op (its own job warms). */
final class WarmCatalogCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $generation) {}

    public function handle(): void
    {
        if (CatalogCache::isCurrent($this->generation)) {
            CatalogCache::warm();
        }
    }
}
