<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ImportConnector;
use App\Events\ImportStageCompleted;
use App\Jobs\WarmCatalogCache;
use App\Models\Category;
use App\Support\CatalogCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CatalogCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_burst_of_flushes_warms_once_with_the_latest_generation(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $category = Category::query()->create(['name' => 'Cache test', 'slug' => 'cache-test', 'active' => 1]);

        CatalogCache::flush();
        CatalogCache::flush();

        $jobs = Queue::pushed(WarmCatalogCache::class)->values();
        $this->assertCount(2, $jobs);
        $this->assertNotNull($jobs[0]->delay, 'the rebuild waits for the burst to end');

        $jobs[0]->handle();
        $this->assertFalse(Cache::has('catalog_filters_cat_'.$category->id), 'a superseded job does nothing');
        $jobs[1]->handle();
        $this->assertTrue(Cache::has('catalog_filters_cat_'.$category->id));
        $this->assertTrue(Cache::has('catalog_navigation_'.$category->id));
        $this->assertTrue(Cache::has('catalog_filters_all'));
    }

    public function test_an_import_stage_flushes_the_storefront_caches(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        Cache::put('product_related_all', 'stale');

        ImportStageCompleted::dispatch(1, ImportConnector::STAGE_PRODUCTS);

        $this->assertFalse(Cache::has('product_related_all'));
        Queue::assertPushed(WarmCatalogCache::class);
    }

    public function test_without_a_queue_worker_nothing_is_queued(): void
    {
        Queue::fake();
        CatalogCache::flush();
        Queue::assertNothingPushed();
    }

    public function test_the_command_warms_now(): void
    {
        Cache::flush();
        $this->artisan('catalog:warm-cache')->assertSuccessful();
        $this->assertTrue(Cache::has('catalog_filters_all'));
    }
}
