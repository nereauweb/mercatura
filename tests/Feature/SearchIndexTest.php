<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ImportConnector;
use App\Events\ImportStageCompleted;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Tests\TestCase;

/** A switched-off product never reaches the visitor through the search, even while the index still holds it. */
final class SearchIndexTest extends TestCase
{
    use DatabaseTransactions;

    private function spyEngine(): SpySearchEngine
    {
        $engine = new SpySearchEngine;
        app(EngineManager::class)->extend('spy', fn () => $engine);
        config(['scout.driver' => 'spy']);

        return $engine;
    }

    private function product(string $sku, bool $active): Product
    {
        return Product::query()->create(['sku' => $sku, 'source' => 'own', 'source_sku' => $sku, 'name' => 'Borraccia '.$sku, 'slug' => 'borraccia-'.strtolower($sku), 'active' => $active ? 1 : 0, 'forced_status' => 'none']);
    }

    public function test_suggestions_skip_products_switched_off_since_the_last_sync(): void
    {
        $engine = $this->spyEngine();
        $on = $this->product('SRC-ON', true);
        $off = $this->product('SRC-OFF', false);
        $engine->hits = [$on->id, $off->id];

        $skus = collect($this->getJson('/search/suggest/borraccia')->assertOk()->json())->pluck('sku')->all();

        $this->assertSame(['SRC-ON'], $skus);
    }

    public function test_an_import_drops_switched_off_products_from_the_index(): void
    {
        $engine = $this->spyEngine();
        $this->product('SRC-ON2', true);
        $off = $this->product('SRC-OFF2', false);
        $engine->deleted = []; // saving a switched-off product already unindexes it; the import updates by query

        ImportStageCompleted::dispatch(1, ImportConnector::STAGE_PRODUCTS);

        $this->assertContains($off->id, $engine->deleted);
    }

    public function test_the_legacy_search_endpoint_lists_active_products_only(): void
    {
        $this->product('SRC-ON3', true);
        $this->product('SRC-OFF3', false);

        $skus = collect($this->getJson('/search/SRC-O')->assertOk()->json())->pluck('sku')->all();

        $this->assertContains('SRC-ON3', $skus);
        $this->assertNotContains('SRC-OFF3', $skus);
    }
}

/** Answers every search with $hits and records deletions. */
final class SpySearchEngine extends NullEngine
{
    /** @var list<int> */
    public array $hits = [];

    /** @var list<int> */
    public array $deleted = [];

    public function delete($models)
    {
        $this->deleted = [...$this->deleted, ...array_map('intval', $models->modelKeys())];
    }

    public function map(Builder $builder, $results, $model)
    {
        return $model->newQuery()->whereIn('id', $this->hits)->get();
    }

    public function keys(Builder $builder)
    {
        return new Collection($this->hits);
    }
}
